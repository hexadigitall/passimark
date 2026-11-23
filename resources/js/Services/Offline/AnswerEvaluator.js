/**
 * Passimark Client-Side Answer Evaluation Service
 * Mirrors server-side App\Services\AnswerEvaluator for 100% offline exam scoring across all 10 item types.
 */

export class AnswerEvaluator {
  /**
   * Evaluates candidate submission for any question type.
   */
  static evaluate(question, submission) {
    const type = question.type || 'single_choice';

    switch (type) {
      case 'multiple_choice':
        return this.evaluateMultipleChoice(question, submission);
      case 'drag_drop':
        return this.evaluateDragDrop(question, submission);
      case 'hotspot':
        return this.evaluateHotspot(question, submission);
      case 'build_list':
        return this.evaluateBuildList(question, submission);
      case 'fill_blank':
        return this.evaluateFillBlank(question, submission);
      case 'cloze':
        return this.evaluateCloze(question, submission);
      case 'create_tree':
        return this.evaluateCreateTree(question, submission);
      default:
        return this.evaluateSingleChoice(question, submission);
    }
  }

  static evaluateSingleChoice(question, submission) {
    let selected = String(submission.selected || '').trim();
    if (!selected && Array.isArray(submission.selections) && submission.selections.length > 0) {
      selected = String(submission.selections[0]).trim();
    }

    const options = question.options || [];
    const correctOpt = options.find(o => o.is_correct);
    const expected = String(question.correct_key || correctOpt?.key || '').trim();

    const isCorrect = expected !== '' && selected.toLowerCase() === expected.toLowerCase();

    return {
      is_correct: isCorrect,
      score: isCorrect ? 1.0 : 0.0,
      selected_summary: selected,
    };
  }

  static evaluateMultipleChoice(question, submission) {
    const chosen = (submission.selections || [])
      .map(s => String(s).trim().toUpperCase())
      .filter(Boolean)
      .sort();

    const options = question.options || [];
    const expected = options
      .filter(o => o.is_correct)
      .map(o => String(o.key).trim().toUpperCase())
      .sort();

    const isCorrect = expected.length > 0 &&
      chosen.length === expected.length &&
      chosen.every((val, idx) => val === expected[idx]);

    // Partial credit calculation
    const matchedCount = chosen.filter(k => expected.includes(k)).length;
    const score = expected.length > 0 ? Math.max(0, matchedCount / expected.length) : (isCorrect ? 1.0 : 0.0);

    return {
      is_correct: isCorrect,
      score: isCorrect ? 1.0 : Number(score.toFixed(2)),
      selected_summary: chosen.join(', '),
    };
  }

  static evaluateDragDrop(question, submission) {
    const payload = question.interactive_payload || {};
    const expectedMapping = payload.correct_mapping || payload.matches || {};
    const candidateMapping = submission.interaction_response?.mapping || submission.mapping || {};

    const targetSlots = Object.keys(expectedMapping);
    if (targetSlots.length === 0) return { is_correct: false, score: 0 };

    let correctSlots = 0;
    for (const slot of targetSlots) {
      if (String(candidateMapping[slot] || '').trim() === String(expectedMapping[slot] || '').trim()) {
        correctSlots++;
      }
    }

    const isCorrect = correctSlots === targetSlots.length;
    return {
      is_correct: isCorrect,
      score: isCorrect ? 1.0 : Number((correctSlots / targetSlots.length).toFixed(2)),
      selected_summary: `${correctSlots}/${targetSlots.length} slots mapped`,
    };
  }

  static evaluateHotspot(question, submission) {
    const payload = question.interactive_payload || {};
    const targets = payload.targets || payload.hotspots || [];
    const click = submission.interaction_response?.point || submission.point || {};

    const cx = Number(click.x ?? -1);
    const cy = Number(click.y ?? -1);

    if (cx < 0 || cy < 0 || targets.length === 0) {
      return { is_correct: false, score: 0.0, selected_summary: `(${cx}, ${cy})` };
    }

    let isHit = false;
    for (const target of targets) {
      if (target.shape === 'circle' || target.radius) {
        const dist = Math.sqrt(Math.pow(cx - target.x, 2) + Math.pow(cy - target.y, 2));
        if (dist <= target.radius) {
          isHit = true;
          break;
        }
      } else if (target.x1 !== undefined && target.x2 !== undefined) {
        if (cx >= target.x1 && cx <= target.x2 && cy >= target.y1 && cy <= target.y2) {
          isHit = true;
          break;
        }
      }
    }

    return {
      is_correct: isHit,
      score: isHit ? 1.0 : 0.0,
      selected_summary: `(${cx.toFixed(1)}, ${cy.toFixed(1)})`,
    };
  }

  static evaluateBuildList(question, submission) {
    const payload = question.interactive_payload || {};
    const expectedSequence = (payload.correct_sequence || payload.sequence || []).map(String);
    const candidateSequence = (submission.interaction_response?.sequence || submission.sequence || []).map(String);

    if (expectedSequence.length === 0) return { is_correct: false, score: 0 };

    const isCorrect = expectedSequence.length === candidateSequence.length &&
      expectedSequence.every((val, idx) => val === candidateSequence[idx]);

    return {
      is_correct: isCorrect,
      score: isCorrect ? 1.0 : 0.0,
      selected_summary: candidateSequence.join(' -> '),
    };
  }

  static evaluateFillBlank(question, submission) {
    const payload = question.interactive_payload || {};
    const acceptable = (payload.acceptable_answers || payload.answers || []).map(a => String(a).trim().toLowerCase());
    const rawAnswer = String(submission.interaction_response?.text || submission.text || submission.selected || '').trim().toLowerCase();

    let isCorrect = acceptable.includes(rawAnswer);

    // Regex check if regex pattern configured
    if (!isCorrect && payload.regex) {
      try {
        const re = new RegExp(payload.regex, 'i');
        isCorrect = re.test(rawAnswer);
      } catch (e) {}
    }

    return {
      is_correct: isCorrect,
      score: isCorrect ? 1.0 : 0.0,
      selected_summary: rawAnswer,
    };
  }

  static evaluateCloze(question, submission) {
    const payload = question.interactive_payload || {};
    const expectedTokens = payload.correct_tokens || payload.answers || {};
    const candidateTokens = submission.interaction_response?.tokens || submission.tokens || {};

    const tokenKeys = Object.keys(expectedTokens);
    if (tokenKeys.length === 0) return { is_correct: false, score: 0 };

    let correctCount = 0;
    for (const key of tokenKeys) {
      if (String(candidateTokens[key] || '').trim().toLowerCase() === String(expectedTokens[key] || '').trim().toLowerCase()) {
        correctCount++;
      }
    }

    const isCorrect = correctCount === tokenKeys.length;
    return {
      is_correct: isCorrect,
      score: isCorrect ? 1.0 : Number((correctCount / tokenKeys.length).toFixed(2)),
      selected_summary: `${correctCount}/${tokenKeys.length} tokens`,
    };
  }

  static evaluateCreateTree(question, submission) {
    const payload = question.interactive_payload || {};
    const expectedEdges = payload.correct_edges || payload.tree || [];
    const candidateEdges = submission.interaction_response?.edges || submission.edges || [];

    const expectedSet = new Set(expectedEdges.map(e => `${e.parent}->${e.child}`));
    const candidateSet = new Set(candidateEdges.map(e => `${e.parent}->${e.child}`));

    let isCorrect = expectedSet.size === candidateSet.size;
    if (isCorrect) {
      for (const edge of expectedSet) {
        if (!candidateSet.has(edge)) {
          isCorrect = false;
          break;
        }
      }
    }

    return {
      is_correct: isCorrect,
      score: isCorrect ? 1.0 : 0.0,
      selected_summary: `${candidateEdges.length} connections`,
    };
  }
}
