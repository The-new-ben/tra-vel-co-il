(function () {
  'use strict';

  // Voice dock (theme 1.29.0). One small module, no dependencies, enqueued
  // only on templates that render a globe. Speech recognition happens inside
  // the browser's own SpeechRecognition engine; this file never records,
  // stores or transmits audio, and the only navigation it performs carries
  // plain text to the planner page. Scroll law: no wheel, touchmove or
  // scroll listeners of any kind.

  const COPY = {
    listening: 'מקשיבים. אפשר לדבר בקצב שלכם.',
    stopped: 'ההקלטה נעצרה. אפשר לערוך את הטקסט, להקליט שוב או לצאת לדרך.',
    micStart: 'התחילו הקלטה',
    micStop: 'עצרו את ההקלטה',
    noSpeech: 'לא נקלט דיבור הפעם. אפשר לנסות שוב או להקליד.',
    denied: 'אין הרשאה למיקרופון בדפדפן הזה, אז ממשיכים בהקלדה.',
    unsupported: 'הקלדה זמינה כאן, זיהוי דיבור אינו נתמך בדפדפן הזה',
    interrupted: 'ההקלטה נקטעה. אפשר לנסות שוב או להקליד.',
    emptyGo: 'כתבו או הקליטו כמה מילים לפני היציאה לדרך.'
  };

  function speechRecognitionConstructor() {
    return window.SpeechRecognition || window.webkitSpeechRecognition || null;
  }

  function speechSupported() {
    return Boolean(speechRecognitionConstructor());
  }

  function plannerBaseUrl() {
    const settings = window.traVelV2 || {};
    if (settings.tripCareUrl) return String(settings.tripCareUrl);
    if (settings.homeUrl) return String(settings.homeUrl).replace(/\/+$/, '') + '/ai-planner/';
    return '/ai-planner/';
  }

  function buildGoUrl(text, spoken) {
    let url;
    try {
      url = new URL(plannerBaseUrl(), window.location.href);
    } catch (error) {
      url = new URL('/ai-planner/', window.location.href);
    }
    url.searchParams.set('voice_prompt', String(text));
    url.searchParams.set('voice', spoken ? '1' : '0');
    return url.toString();
  }

  function setupDock(dock) {
    const toggle = dock.querySelector('[data-voice-dock-toggle]');
    const sheet = dock.querySelector('[data-voice-sheet]');
    const interim = dock.querySelector('[data-voice-interim]');
    const text = dock.querySelector('[data-voice-text]');
    const note = dock.querySelector('[data-voice-note]');
    const mic = dock.querySelector('[data-voice-mic]');
    const micLabel = dock.querySelector('[data-voice-mic-label]');
    const go = dock.querySelector('[data-voice-go]');
    const cancel = dock.querySelector('[data-voice-cancel]');
    if (!toggle || !sheet || !interim || !text || !note || !mic || !go || !cancel) return;

    const state = { open: false, listening: false, spokenText: false, textMode: !speechSupported(), recognition: null };

    function setNote(message) {
      note.textContent = message || '';
      note.hidden = !message;
    }

    function enterTextMode(message) {
      state.textMode = true;
      stopListening();
      mic.hidden = true;
      dock.dataset.state = state.open ? 'open' : 'idle';
      setNote(message);
    }

    function setListening(active) {
      state.listening = active;
      mic.setAttribute('aria-pressed', String(active));
      mic.classList.toggle('is-listening', active);
      if (micLabel) micLabel.textContent = active ? COPY.micStop : COPY.micStart;
      if (active) setNote(COPY.listening);
      if (!active) interim.textContent = '';
    }

    function ensureRecognition() {
      if (state.recognition || state.textMode) return state.recognition;
      const Recognition = speechRecognitionConstructor();
      if (!Recognition) return null;
      const recognition = new Recognition();
      recognition.lang = 'he-IL';
      recognition.interimResults = true;
      recognition.continuous = false;
      recognition.maxAlternatives = 1;
      recognition.addEventListener('start', () => setListening(true));
      recognition.addEventListener('end', () => {
        if (!state.listening) return;
        setListening(false);
        setNote(COPY.stopped);
      });
      recognition.addEventListener('error', event => {
        setListening(false);
        if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
          enterTextMode(COPY.denied);
          text.focus();
          return;
        }
        setNote(event.error === 'no-speech' ? COPY.noSpeech : COPY.interrupted);
      });
      recognition.addEventListener('result', event => {
        let interimText = '';
        let finalText = '';
        for (let index = event.resultIndex; index < event.results.length; index += 1) {
          const chunk = event.results[index][0] ? event.results[index][0].transcript : '';
          if (event.results[index].isFinal) finalText += chunk;
          else interimText += chunk;
        }
        interim.textContent = interimText.trim();
        if (!finalText.trim()) return;
        const existing = text.value.trim();
        text.value = (existing ? existing + ' ' : '') + finalText.trim();
        state.spokenText = true;
        interim.textContent = '';
      });
      state.recognition = recognition;
      return recognition;
    }

    function startListening() {
      const recognition = ensureRecognition();
      if (!recognition) return;
      try {
        recognition.start();
      } catch (error) {
        // Already listening: leave the current session running.
      }
    }

    function stopListening() {
      if (state.recognition && state.listening) {
        setListening(false);
        try {
          state.recognition.stop();
        } catch (error) {
          // The engine already ended on its own.
        }
      }
    }

    function handleDocumentKeydown(event) {
      if (event.key !== 'Escape' || !state.open) return;
      event.preventDefault();
      closeSheet(true);
    }

    function openSheet() {
      if (state.open) return;
      state.open = true;
      sheet.hidden = false;
      dock.dataset.state = 'open';
      toggle.setAttribute('aria-expanded', 'true');
      document.addEventListener('keydown', handleDocumentKeydown, true);
      if (state.textMode) {
        mic.hidden = true;
        setNote(COPY.unsupported);
        text.focus();
        return;
      }
      startListening();
      mic.focus();
    }

    function closeSheet(returnFocus) {
      if (!state.open) return;
      state.open = false;
      stopListening();
      sheet.hidden = true;
      dock.dataset.state = 'idle';
      toggle.setAttribute('aria-expanded', 'false');
      document.removeEventListener('keydown', handleDocumentKeydown, true);
      if (returnFocus) toggle.focus();
    }

    toggle.addEventListener('click', () => {
      if (state.open) closeSheet(true);
      else openSheet();
    });
    mic.addEventListener('click', () => {
      if (state.listening) {
        stopListening();
        setNote(COPY.stopped);
      } else {
        startListening();
      }
    });
    text.addEventListener('input', () => {
      if (!text.value.trim()) state.spokenText = false;
    });
    go.addEventListener('click', () => {
      const request = text.value.replace(/\s+/g, ' ').trim();
      if (!request) {
        setNote(COPY.emptyGo);
        text.focus();
        return;
      }
      stopListening();
      go.disabled = true;
      window.location.assign(buildGoUrl(request, state.spokenText));
    });
    cancel.addEventListener('click', () => closeSheet(true));
    window.addEventListener('pageshow', event => {
      if (event.persisted) go.disabled = false;
    });

    if (state.textMode) mic.hidden = true;
    dock.hidden = false;
  }

  function init() {
    const docks = document.querySelectorAll('[data-voice-dock]');
    docks.forEach(dock => setupDock(dock));
  }

  window.traVelVoiceDock = { buildGoUrl, speechSupported };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
