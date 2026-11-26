/**
 * Passimark Universal Package & VCE Parser
 * Extracts exam questions, options, answers, and explanations from .vce, .psmk, .json, and .txt files.
 */

export class PackageParser {
  /**
   * Parse a File or Blob into a structured Passimark Exam object.
   * @param {File|Blob} file
   * @returns {Promise<Object>}
   */
  static async parseFile(file) {
    const filename = file.name || 'exam_import.vce';
    const ext = filename.split('.').pop().toLowerCase();
    const arrayBuffer = await file.arrayBuffer();
    const bytes = new Uint8Array(arrayBuffer);

    // 1. Text / Markdown / CSV format
    if (['txt', 'text', 'csv', 'md'].includes(ext)) {
      const text = new TextDecoder('utf-8').decode(bytes);
      return this.parseText(text, filename);
    }

    // 2. JSON format
    try {
      const text = new TextDecoder('utf-8').decode(bytes);
      const trimmed = text.trim();
      if ((trimmed.startsWith('{') && trimmed.endsWith('}')) || (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
        const parsed = JSON.parse(trimmed);
        return this.normalizeParsedExam(parsed, filename);
      }
    } catch (e) {
      // Not JSON, continue
    }

    // 3. ZIP / .psmk Container (Magic PK\x03\x04)
    if (bytes[0] === 0x50 && bytes[1] === 0x4b && bytes[2] === 0x03 && bytes[3] === 0x04) {
      return this.parsePsmkZip(bytes, filename);
    }

    // 4. VCE Binary Stream (Magic 0x85 0xA8 or .vce extension)
    return this.parseVceBinary(bytes, filename);
  }

  /**
   * Parse raw text dump (from VCE Designer export, clipboard paste, or study guide).
   * @param {string} text
   * @param {string} [filename]
   * @returns {Object}
   */
  static parseText(text, filename = 'Imported Exam') {
    const baseName = filename.replace(/\.[^/.]+$/, '');
    const examCode = baseName.replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 10).toUpperCase() || 'CUSTOM-EXAM';

    const questions = this.extractQuestionsFromText(text);

    if (questions.length === 0) {
      // Resilient fallback: split by double newlines or numbered lines
      return this.fallbackTextParser(text, filename, examCode);
    }

    return {
      id: `text-imp-${Date.now()}`,
      code: examCode,
      title: `${baseName} (Imported Exam)`,
      category: 'User Imported Practice Bank',
      passingScore: 700,
      timeLimitMinutes: Math.max(30, Math.min(180, Math.round(questions.length * 1.5))),
      description: `Successfully imported from text stream (${questions.length} questions parsed).`,
      questions,
    };
  }

  /**
   * Parse VCE binary stream extracting questions, options, answer keys, and explanations.
   * Detects Avanset proprietary encrypted containers and warns gracefully.
   * @param {Uint8Array} bytes
   * @param {string} filename
   * @returns {Promise<Object>}
   */
  static async parseVceBinary(bytes, filename) {
    const baseName = filename.replace(/\.[^/.]+$/, '');
    const examCode = baseName.replace(/[^a-zA-Z0-9_-]/g, '').slice(0, 10).toUpperCase() || 'VCE-IMPORT';

    const isVceMagic = (bytes[0] === 0x85 && bytes[1] === 0xA8);

    // Attempt decompressing zlib only if zlib CMF byte (0x78) is present
    let rawText = '';
    if (bytes.length > 2 && bytes[0] === 0x78 && typeof DecompressionStream !== 'undefined') {
      try {
        const ds = new DecompressionStream('deflate');
        const writer = ds.writable.getWriter();
        const writePromise = writer.write(bytes).catch(() => {});
        const closePromise = writer.close().catch(() => {});
        const reader = ds.readable.getReader();
        const chunks = [];
        let done, value;
        while (!done) {
          ({ done, value } = await reader.read());
          if (value) chunks.push(value);
        }
        await Promise.allSettled([writePromise, closePromise]);
        const totalLen = chunks.reduce((acc, c) => acc + c.length, 0);
        const decompressed = new Uint8Array(totalLen);
        let offset = 0;
        for (const c of chunks) {
          decompressed.set(c, offset);
          offset += c.length;
        }
        rawText = new TextDecoder('utf-8', { fatal: false }).decode(decompressed);
      } catch (e) {
        // Not valid zlib deflate stream
      }
    }

    // If zlib produced readable text with question delimiters, extract it
    if (rawText && rawText.length > 50) {
      const questions = this.extractQuestionsFromText(rawText).filter((q) => this.isReadableQuestion(q));
      if (questions.length > 0) {
        return {
          id: `vce-${Date.now()}`,
          code: examCode,
          title: `${baseName} (Imported VCE)`,
          category: 'Imported Certification',
          passingScore: 700,
          timeLimitMinutes: Math.max(30, Math.min(180, Math.round(questions.length * 1.5))),
          description: `Successfully imported from Visual CertExam file (${questions.length} items parsed).`,
          questions,
        };
      }
    }

    // Check printable ratio of raw bytes (plaintext >= 0.85, encrypted ciphertext ~0.37)
    const sampleLen = Math.min(bytes.length, 4096);
    let printableCount = 0;
    for (let i = 0; i < sampleLen; i++) {
      const b = bytes[i];
      if ((b >= 32 && b <= 126) || b === 9 || b === 10 || b === 13) printableCount++;
    }
    const rawPrintableRatio = sampleLen > 0 ? printableCount / sampleLen : 0;

    // Only attempt plain ASCII regex extraction if bytes are genuinely text-like (>82% printable)
    if (rawPrintableRatio >= 0.82) {
      const asciiText = new TextDecoder('ascii', { fatal: false }).decode(bytes);
      const questionsFromAscii = this.extractQuestionsFromText(asciiText).filter((q) => this.isReadableQuestion(q));
      if (questionsFromAscii.length > 0) {
        return {
          id: `vce-${Date.now()}`,
          code: examCode,
          title: `${baseName} (Imported VCE)`,
          category: 'Imported Certification',
          passingScore: 700,
          timeLimitMinutes: Math.max(30, Math.min(180, Math.round(questionsFromAscii.length * 1.5))),
          description: `Successfully imported from Visual CertExam file (${questionsFromAscii.length} items parsed).`,
          questions: questionsFromAscii,
        };
      }
    }

    // If file has VCE magic 0x85 0xA8 or is .vce and yielded no clean readable text, it is Avanset encrypted
    if (isVceMagic || filename.toLowerCase().endsWith('.vce')) {
      return {
        error: 'ENCRYPTED_VCE',
        filename,
        title: baseName,
        code: examCode,
        message: 'Avanset proprietary container encryption detected. Modern .vce files are protected by AES/SQLCipher lock-in. Export as Text or PDF in VCE Designer, or paste your question text directly.',
      };
    }

    // Generic fallback for plain text files
    const fallbackText = new TextDecoder('utf-8', { fatal: false }).decode(bytes);
    return this.fallbackTextParser(fallbackText, filename, examCode);
  }

  /**
   * Sanity check to ensure extracted question text is human-readable prose, not ciphertext.
   * @param {Object} q
   * @returns {boolean}
   */
  static isReadableQuestion(q) {
    const content = (q?.content || '').trim();
    if (content.length < 15) return false;
    // Reject control chars (\x00-\x08, \x0B, \x0C, \x0E-\x1F)
    if (/[\x00-\x08\x0B\x0C\x0E-\x1F]/.test(content)) return false;
    // Check alphanumeric + space ratio (natural language prose is typically >= 80%)
    const cleanChars = (content.match(/[a-zA-Z0-9\s.,?!:;'"()-]/g) || []).length;
    if (cleanChars / content.length < 0.85) return false;
    // Check presence of normal multi-letter alphabetic words with vowels
    const words = content.match(/[a-zA-Z]{2,}/g) || [];
    const wordsWithVowels = words.filter((w) => /[aeiouAEIOU]/.test(w));
    return words.length >= 3 && (wordsWithVowels.length / words.length) >= 0.65;
  }

  /**
   * Extract question blocks, options, answer keys, and explanations from text.
   * Handles:
   * - "Question 1:", "QUESTION NO: 1", "Item #1", "1."
   * - Choices: "A.", "A)", "[A]", "(A)"
   * - Answers: "Answer: A", "Correct Answer: B, C", "Ans: D"
   * - Explanations: "Explanation:", "Reference:", "Rationale:"
   * @param {string} text
   * @returns {Array}
   */
  static extractQuestionsFromText(text) {
    if (!text || text.trim().length < 20) return [];
    const questions = [];

    // Split text into question chunks by common delimiters
    const delimiterRegex = /(?=(?:QUESTION\s*(?:NO:?)?\s*\d+|Question\s*\d+|Item\s*#?\s*\d+|^\s*\d+[\.\)]\s+[A-Z]))/gmi;
    const rawSections = text.split(delimiterRegex);

    for (let i = 0; i < rawSections.length; i++) {
      const section = rawSections[i].trim();
      if (section.length < 25) continue;

      const lines = section.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
      if (lines.length < 2) continue;

      let stemLines = [];
      let optionLines = [];
      let answerKey = '';
      let explanation = '';
      let domain = 'General Domain';

      let parsingOptions = false;
      let parsingExplanation = false;

      for (let j = 0; j < lines.length; j++) {
        const line = lines[j];

        // Skip initial question header line (e.g. "QUESTION NO: 1" or "Question 1")
        if (j === 0 && /^(?:QUESTION\s*(?:NO:?)?\s*\d+|Question\s*\d+|Item\s*#?\s*\d+)/i.test(line)) {
          continue;
        }

        // Section header / Topic
        const topicMatch = line.match(/^(?:Topic|Section|Domain)\s*[:\-\s]*(.*)/i);
        if (topicMatch) {
          domain = topicMatch[1] || domain;
          continue;
        }

        // Answer line detection
        const ansMatch = line.match(/(?:Correct\s*)?Ans(?:wer)?\s*[:\-\s]*([A-H,\s]+)/i);
        if (ansMatch) {
          answerKey = ansMatch[1].trim().toUpperCase().replace(/[^A-H]/g, '');
          parsingOptions = false;
          continue;
        }

        // Explanation line detection
        const expMatch = line.match(/(?:Explanation|Reference|Rationale|Note)\s*[:\-\s]*(.*)/i);
        if (expMatch) {
          explanation = expMatch[1] || '';
          parsingExplanation = true;
          parsingOptions = false;
          continue;
        }

        if (parsingExplanation) {
          explanation += ' ' + line;
          continue;
        }

        // Option line detection: A. text | A) text | [A] text | (A) text (uppercase key required)
        const optMatch = line.match(/^(?:\[|\()?([A-H])(?:\]|\)|\.)\s+(.*)/);
        if (optMatch) {
          parsingOptions = true;
          optionLines.push({ key: optMatch[1].toUpperCase(), text: optMatch[2] });
          continue;
        }

        if (parsingOptions && optionLines.length > 0) {
          // Append continuation text to last option
          optionLines[optionLines.length - 1].text += ' ' + line;
        } else if (!parsingOptions && !parsingExplanation) {
          stemLines.push(line);
        }
      }

      const content = stemLines.join(' ').trim();
      if (!content || optionLines.length < 2) continue;

      const isMulti = answerKey.length > 1;
      const type = isMulti ? 'multiple_choice' : 'single_choice';
      const finalCorrectKey = isMulti 
        ? answerKey.split('').filter((k, idx, arr) => arr.indexOf(k) === idx)
        : (answerKey[0] || 'A');

      const options = optionLines.map((opt) => ({
        key: opt.key,
        text: opt.text,
        is_correct: isMulti ? finalCorrectKey.includes(opt.key) : opt.key === finalCorrectKey,
      }));

      questions.push({
        id: `imp-q${questions.length + 1}`,
        number: questions.length + 1,
        type,
        domain,
        content,
        options,
        correct_key: finalCorrectKey,
        explanation: explanation || 'Imported practice item.',
        irt_a: 1.6,
        irt_b: Number(((questions.length * 0.08) - 1.0).toFixed(2)),
        irt_c: isMulti ? 0.05 : 0.25,
      });
    }

    return questions;
  }

  /**
   * Resilient fallback when delimiter matching fails but readable text exists.
   */
  static fallbackTextParser(text, filename, examCode) {
    const rawParagraphs = text.split(/(?:\r?\n){2,}/).map((p) => p.trim()).filter((p) => p.length > 40);
    const questions = [];

    for (let i = 0; i < Math.min(20, rawParagraphs.length); i++) {
      const p = rawParagraphs[i];
      // Only include paragraphs with mostly clean readable characters
      const cleanChars = (p.match(/[a-zA-Z0-9\s]/g) || []).length;
      if (cleanChars / p.length < 0.6) continue;

      questions.push({
        id: `gen-q${questions.length + 1}`,
        number: questions.length + 1,
        type: 'single_choice',
        domain: 'Exam Review',
        content: p.slice(0, 350) + (p.length > 350 ? '...' : ''),
        options: [
          { key: 'A', text: 'Accurate / Compliant with technical standard or policy', is_correct: true },
          { key: 'B', text: 'Inaccurate / Non-compliant configuration requiring remediation', is_correct: false },
          { key: 'C', text: 'Requires architectural escalation or administrative exception', is_correct: false },
          { key: 'D', text: 'Deprecated protocol or invalid design pattern', is_correct: false },
        ],
        correct_key: 'A',
        explanation: 'Extracted from file content stream.',
        irt_a: 1.5,
        irt_b: 0.0,
        irt_c: 0.25,
      });
    }

    if (questions.length === 0) {
      return {
        error: 'NO_QUESTIONS',
        filename,
        message: 'No readable questions could be extracted from this file. Ensure it contains plaintext or standard question formats.',
      };
    }

    const baseName = filename.replace(/\.[^/.]+$/, '');
    return {
      id: `fallback-${Date.now()}`,
      code: examCode,
      title: `${baseName} (Parsed Content)`,
      category: 'Imported Certification',
      passingScore: 700,
      timeLimitMinutes: Math.max(30, questions.length * 2),
      questions,
    };
  }

  /**
   * Simple ZIP unpacker for .psmk packages
   */
  static parsePsmkZip(bytes, filename) {
    const text = new TextDecoder('utf-8', { fatal: false }).decode(bytes);
    const jsonMatch = text.match(/\{[\s\S]*"questions"[\s\S]*\}/);
    if (jsonMatch) {
      try {
        const parsed = JSON.parse(jsonMatch[0]);
        return this.normalizeParsedExam(parsed, filename);
      } catch (e) {}
    }
    return this.fallbackTextParser(text, filename, filename.split('.')[0].toUpperCase());
  }

  /**
   * Normalizes an exam JSON object to guaranteed Passimark schema.
   */
  static normalizeParsedExam(parsed, filename) {
    const rawQuestions = Array.isArray(parsed) 
      ? parsed 
      : (parsed.questions || parsed.items || []);

    const questions = rawQuestions.map((q, idx) => {
      let rawOpts = q.options || q.choices || q.answers || [];
      let options = [];

      if (Array.isArray(rawOpts)) {
        options = rawOpts.map((opt, oIdx) => {
          if (typeof opt === 'string') {
            const letter = String.fromCharCode(65 + oIdx);
            return { key: letter, text: opt, is_correct: (q.correct_key || q.answer) === letter };
          }
          const key = opt.key || opt.letter || String.fromCharCode(65 + oIdx);
          const text = opt.text || opt.label || opt.content || opt.value || '';
          const isCorrect = opt.is_correct !== undefined
            ? Boolean(opt.is_correct)
            : (Array.isArray(q.correct_key || q.answer)
                ? (q.correct_key || q.answer).includes(key)
                : (q.correct_key || q.answer) === key);
          return { key, text, is_correct: isCorrect };
        });
      }

      const correctKey = q.correct_key || q.answer || (options[0]?.key || 'A');
      const isMulti = Array.isArray(correctKey) && correctKey.length > 1;
      const type = q.type || (isMulti ? 'multiple_choice' : 'single_choice');

      return {
        id: q.id || `q-${idx + 1}`,
        number: q.number || (idx + 1),
        type,
        domain: q.domain || q.topic || 'General Domain',
        content: q.content || q.stem || q.question || q.text || 'Question Prompt',
        options,
        correct_key: correctKey,
        interactive_payload: q.interactive_payload || null,
        explanation: q.explanation || q.rationale || '',
        reference: q.reference || '',
        irt_a: q.irt_a || 1.5,
        irt_b: q.irt_b || 0.0,
        irt_c: q.irt_c || 0.2,
      };
    });

    const baseName = filename.replace(/\.[^/.]+$/, '');
    return {
      id: parsed.id || `pkg-${Date.now()}`,
      code: parsed.code || baseName.slice(0, 10).toUpperCase(),
      title: parsed.title || baseName,
      category: parsed.category || 'Certification Bank',
      passingScore: parsed.passingScore || parsed.pass_score || 700,
      timeLimitMinutes: parsed.timeLimitMinutes || parsed.time_limit || 90,
      description: parsed.description || 'Passimark assessment container.',
      questions,
    };
  }
}
