/**
 * Passimark Client-Side IRT 3PL & Adaptive Testing Engine
 * Mathematically identical to App\Services\Irt\Irt3PL for zero-latency, 100% offline CAT exams.
 */

export const THETA_MIN = -3.0;
export const THETA_MAX = 3.0;
export const EPS = 1e-8;

export class CatEngine {
  /**
   * 3PL probability of a correct response: P(theta) = c + (1 - c) / (1 + exp(-a * (theta - b)))
   */
  static p(params, theta) {
    const a = Number(params.a ?? params.discrimination ?? 1.0);
    const b = Number(params.b ?? params.difficulty ?? 0.0);
    let c = Number(params.c ?? params.guessing ?? 0.25);
    c = Math.max(0.0, Math.min(0.99, c));

    const exponent = -a * (theta - b);
    const e = Math.exp(exponent);
    return c + (1 - c) / (1 + e);
  }

  /**
   * Fisher information for one item at theta:
   * I(theta) = a^2 * (P - c)^2 * (1 - P) / (P * (1 - c)^2)
   */
  static information(params, theta) {
    const a = Number(params.a ?? params.discrimination ?? 1.0);
    let c = Number(params.c ?? params.guessing ?? 0.25);
    c = Math.max(0.0, Math.min(0.99, c));

    const prob = this.p(params, theta);
    const den = prob * (1 - c) * (1 - c);
    if (den <= EPS) return 0.0;

    const info = (a * a * Math.pow(prob - c, 2) * (1 - prob)) / den;
    return Math.max(0.0, info);
  }

  /**
   * First derivative of the joint log-likelihood (score) at theta.
   * answers: array of { a, b, c, u } where u is 1 for correct, 0 for incorrect.
   */
  static score(answers, theta) {
    let total = 0.0;
    for (const ans of answers) {
      const a = Number(ans.a ?? ans.discrimination ?? 1.0);
      let c = Number(ans.c ?? ans.guessing ?? 0.25);
      c = Math.max(0.0, Math.min(0.99, c));
      const u = Number(ans.u ?? ans.is_correct ? 1 : 0);

      const prob = Math.max(EPS, Math.min(1 - EPS, this.p(ans, theta)));
      const den = prob * (1 - prob) * (1 - c);
      if (den <= EPS) continue;

      total += (a * (1 - prob) * (prob - c) * (u - prob)) / den;
    }
    return total;
  }

  /**
   * Total Fisher information across answered items at theta.
   */
  static totalInformation(answers, theta) {
    let total = 0.0;
    for (const ans of answers) {
      total += this.information(ans, theta);
    }
    return total;
  }

  /**
   * Standard error of theta estimate: SE = 1 / sqrt(I(theta))
   */
  static standardError(answers, theta) {
    const info = this.totalInformation(answers, theta);
    if (info <= EPS) return null;
    return 1 / Math.sqrt(info);
  }

  /**
   * Clamp theta to psychometric limits [-3.0, +3.0].
   */
  static clamp(theta) {
    return Math.max(THETA_MIN, Math.min(THETA_MAX, theta));
  }

  /**
   * Newton-Raphson Maximum Likelihood Estimation (MLE) of candidate ability theta.
   */
  static mle(answers, initialTheta = 0.0, maxIterations = 10) {
    if (!answers || answers.length === 0) {
      return { theta: this.clamp(initialTheta), iterations: 0, converged: true, se: null };
    }

    let theta = this.clamp(initialTheta);
    let result = this.newton(answers, theta, maxIterations);

    if (!result.converged) {
      // Warm starts at bounds can oscillate; retry from scale center
      const restart = this.newton(answers, 0.0, maxIterations);
      if (restart.converged || Math.abs(this.score(answers, restart.theta)) < Math.abs(this.score(answers, result.theta))) {
        result = restart;
      }
    }

    return result;
  }

  static newton(answers, theta, maxIterations) {
    theta = this.clamp(theta);
    let converged = false;
    let iteration = 0;

    for (; iteration < maxIterations; iteration++) {
      const info = this.totalInformation(answers, theta);
      const scr = this.score(answers, theta);

      let step = 0.0;
      if (info > EPS) {
        step = scr / info;
      } else if (scr > EPS) {
        step = 0.1;
      } else if (scr < -EPS) {
        step = -0.1;
      } else {
        break;
      }

      const next = this.clamp(theta + step);
      if (Math.abs(next - theta) < 0.001) {
        theta = next;
        converged = true;
        break;
      }
      theta = next;
    }

    return {
      theta,
      iterations: iteration + 1,
      converged,
      se: this.standardError(answers, theta),
    };
  }

  /**
   * Selects the next optimal item from the pool by Maximum Fisher Information.
   */
  static selectNextItem(pool, answeredIds = [], currentTheta = 0.0) {
    const answeredSet = new Set(answeredIds.map(Number));
    const available = pool.filter(q => !answeredSet.has(Number(q.id)));

    if (available.length === 0) return null;

    let bestItem = null;
    let maxInfo = -Infinity;

    for (const item of available) {
      const info = this.information({
        a: item.discrimination ?? 1.0,
        b: item.difficulty ?? 0.0,
        c: item.guessing ?? 0.25,
      }, currentTheta);

      if (info > maxInfo) {
        maxInfo = info;
        bestItem = item;
      }
    }

    return bestItem;
  }

  /**
   * Maps theta in [-3, 3] to a 0-100 scaled score (50 at theta = 0).
   */
  static thetaToScore(theta) {
    const clamped = this.clamp(theta);
    return Math.round(((clamped - THETA_MIN) / (THETA_MAX - THETA_MIN)) * 100);
  }
}
