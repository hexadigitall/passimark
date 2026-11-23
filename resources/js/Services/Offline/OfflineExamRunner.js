import { SqliteDatabase } from './SqliteDatabase';
import { CatEngine } from './CatEngine';
import { AnswerEvaluator } from './AnswerEvaluator';
import { SyncQueue } from './SyncQueue';

/**
 * Standalone offline exam runner for client-side CAT and non-linear assessments with zero server dependency.
 */
export class OfflineExamRunner {
  static async getDb() {
    return await SqliteDatabase.getDatabase();
  }

  /**
   * Starts a new offline exam attempt.
   */
  static async startAttempt({ sessionId, examId = null, mode = 'cat', timeMinutes = 90 }) {
    const db = await this.getDb();
    const localId = `local_att_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;

    const questions = await db.query('questions', q => q.session_id == sessionId);
    if (questions.length === 0) {
      throw new Error(`No offline questions cached for session ID ${sessionId}`);
    }

    const attempt = {
      local_id: localId,
      session_id: Number(sessionId),
      exam_id: examId ? Number(examId) : null,
      mode,
      theta: 0.0,
      theta_se: null,
      score: 0.0,
      passed: 0,
      status: 'active',
      time_spent: 0,
      started_at: new Date().toISOString(),
      completed_at: null,
      paused_at: null,
      remaining_seconds: timeMinutes * 60,
      session_state: JSON.stringify({ current_index: 0, flagged: [] }),
      synced: 0,
    };

    await db.insert('attempts', attempt);

    // Initial question delivery
    let initialQuestion = null;
    if (mode === 'cat') {
      initialQuestion = CatEngine.selectNextItem(questions, [], 0.0);
    } else {
      initialQuestion = questions[0];
    }

    return {
      attempt,
      question: initialQuestion,
      total_pool: questions.length,
    };
  }

  /**
   * Submits candidate answer locally with zero network latency.
   */
  static async answerQuestion({ localId, questionId, submission, timeSpent = 10 }) {
    const db = await this.getDb();
    const attempts = await db.query('attempts', a => a.local_id === localId);
    if (attempts.length === 0) throw new Error(`Attempt ${localId} not found.`);
    const attempt = attempts[0];

    const questions = await db.query('questions', q => q.id == questionId);
    if (questions.length === 0) throw new Error(`Question ${questionId} not found.`);
    const question = questions[0];

    // Client-side evaluation
    const evaluation = AnswerEvaluator.evaluate(question, submission);

    // Save answer record
    await db.insert('attempt_answers', {
      attempt_id: localId,
      question_id: Number(questionId),
      is_correct: evaluation.is_correct ? 1 : 0,
      time_spent: Number(timeSpent),
      interaction_response: JSON.stringify(submission),
      created_at: new Date().toISOString(),
    });

    // Update cumulative time spent
    const totalTimeSpent = (attempt.time_spent || 0) + Number(timeSpent);
    let newTheta = attempt.theta || 0.0;
    let newThetaSe = attempt.theta_se || null;

    const answers = await db.query('attempt_answers', ans => ans.attempt_id === localId);
    const answeredIds = answers.map(a => a.question_id);

    // Re-estimate ability if CAT
    if (attempt.mode === 'cat') {
      const irtAnswers = [];
      for (const ans of answers) {
        const qRow = (await db.query('questions', q => q.id == ans.question_id))[0];
        if (qRow) {
          irtAnswers.push({
            a: qRow.discrimination ?? 1.0,
            b: qRow.difficulty ?? 0.0,
            c: qRow.guessing ?? 0.25,
            u: ans.is_correct ? 1 : 0,
          });
        }
      }

      const mleResult = CatEngine.mle(irtAnswers, newTheta);
      newTheta = mleResult.theta;
      newThetaSe = mleResult.se;
    }

    const correctCount = answers.filter(a => a.is_correct).length;
    const currentScore = (correctCount / answers.length) * 100;

    await db.update('attempts', localId, {
      theta: newTheta,
      theta_se: newThetaSe,
      score: currentScore,
      time_spent: totalTimeSpent,
    });

    // Determine next question
    const pool = await db.query('questions', q => q.session_id == attempt.session_id);
    let nextQuestion = null;

    if (attempt.mode === 'cat') {
      nextQuestion = CatEngine.selectNextItem(pool, answeredIds, newTheta);
    } else {
      nextQuestion = pool.find(q => !answeredIds.includes(q.id)) || null;
    }

    return {
      evaluation,
      theta: newTheta,
      theta_se: newThetaSe,
      score: currentScore,
      answered_count: answers.length,
      next_question: nextQuestion,
      is_complete: !nextQuestion,
    };
  }

  /**
   * Finalizes the offline exam attempt and enqueues it for cloud synchronization.
   */
  static async finishAttempt(localId) {
    const db = await this.getDb();
    const attempts = await db.query('attempts', a => a.local_id === localId);
    if (attempts.length === 0) throw new Error(`Attempt ${localId} not found.`);
    const attempt = attempts[0];

    const answers = await db.query('attempt_answers', a => a.attempt_id === localId);
    const sessions = await db.query('sessions', s => s.id == attempt.session_id);
    const session = sessions[0] || {};
    const passScore = session.pass_score || 70;

    const correctCount = answers.filter(a => a.is_correct).length;
    const finalScore = answers.length > 0 ? (correctCount / answers.length) * 100 : 0;
    const passed = finalScore >= passScore ? 1 : 0;

    const completedAt = new Date().toISOString();

    await db.update('attempts', localId, {
      score: finalScore,
      passed,
      status: 'completed',
      completed_at: completedAt,
    });

    // Enqueue in sync queue for background cloud sync
    await SyncQueue.enqueueAttempt({
      local_id: localId,
      session_id: attempt.session_id,
      exam_id: attempt.exam_id,
      mode: attempt.mode,
      theta: attempt.theta,
      theta_se: attempt.theta_se,
      score: finalScore,
      passed: Boolean(passed),
      started_at: attempt.started_at,
      completed_at: completedAt,
      time_spent: attempt.time_spent || 0,
      answers: answers.map(a => ({
        question_id: a.question_id,
        is_correct: Boolean(a.is_correct),
        time_spent: a.time_spent || 0,
        interaction_response: a.interaction_response ? JSON.parse(a.interaction_response) : null,
      })),
    });

    // Trigger immediate sync if network is available
    if (typeof window !== 'undefined' && window.navigator?.onLine) {
      SyncQueue.syncNow().catch(() => {});
    }

    return {
      local_id: localId,
      score: finalScore,
      passed: Boolean(passed),
      answers_count: answers.length,
      correct_count: correctCount,
      theta: attempt.theta,
      completed_at: completedAt,
    };
  }
}
