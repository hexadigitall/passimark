# Sprint 2: Adaptive Exam Flow and Result Scoring

**Status:** Complete
**Date:** 2026-09-02

## Delivered

- Protected exam attempt route for authenticated learners
- CAT question selection using the existing theta-based engine
- Timed assessment UI with countdown and low-time state
- Practice and CAT answer submission through the backend API
- Response persistence in `passimark_attempt_answers`
- Theta updates for CAT attempts
- Automatic completion when the configured question count or available question pool is exhausted
- Stored score, pass/fail result, completion timestamp, and progress attempt count
- Result screen showing score, correct answers, and theta estimate
- Instruction gate before the timer begins
- Dedicated result route with answer review and attempt history
- Automatic timeout completion, including unanswered final questions
- Re-entry into an unfinished attempt instead of creating duplicates
- Retest action after a completed attempt
- Attempt and question ownership checks

## Verification

Command:

```text
npm run build; vendor\\bin\\phpunit
```

Focused exam lifecycle check:

```text
vendor\\bin\\phpunit --filter DashboardSessionActionsTest
```

The focused check completed with 6 tests and 72 assertions. The production asset build and complete PHPUnit suite both passed, so the Sprint 2 exit criteria are met.

## Future enhancements

- Dedicated result route and historical result view
- Exam instructions and submit confirmation
- Question flagging and review navigation
- Better resume timer persistence across browser reloads
- More CAT-specific termination and calibration tests
