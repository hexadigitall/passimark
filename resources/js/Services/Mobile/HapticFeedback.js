/**
 * Passimark Tactile Haptic Feedback Service
 * Triggers ergonomic micro-vibrations on mobile touch devices.
 * Uses navigator.vibrate across Android and modern webviews.
 */

export class HapticFeedback {
  static enabled = true;

  static isSupported() {
    return typeof window !== 'undefined' && typeof window.navigator !== 'undefined' && 'vibrate' in window.navigator;
  }

  /**
   * Light tactile tap on option selection (12ms)
   */
  static selection() {
    if (!this.enabled || !this.isSupported()) return;
    try {
      window.navigator.vibrate(12);
    } catch (e) {}
  }

  /**
   * Medium tactile click on option strikethrough (25ms)
   */
  static strikethrough() {
    if (!this.enabled || !this.isSupported()) return;
    try {
      window.navigator.vibrate(25);
    } catch (e) {}
  }

  /**
   * Success pulse when completing an assessment or milestone
   */
  static success() {
    if (!this.enabled || !this.isSupported()) return;
    try {
      window.navigator.vibrate([20, 40, 30]);
    } catch (e) {}
  }

  /**
   * Double pulse warning for countdown timer checkpoints (e.g. 5 minutes remaining)
   */
  static warning() {
    if (!this.enabled || !this.isSupported()) return;
    try {
      window.navigator.vibrate([40, 50, 40]);
    } catch (e) {}
  }
}
