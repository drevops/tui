import React, {useEffect, useRef, useState} from 'react';
import clsx from 'clsx';
import Layout from '@theme/Layout';
import Head from '@docusaurus/Head';
import {useBaseUrlUtils} from '@docusaurus/useBaseUrl';
import styles from './index.module.css';

/* ────────────────────────────────────────────────────────────────────────
 *  CONFIG - edit these freely.
 *
 *  TITLE_PHRASES are the rotating hero headlines. They are typed out one
 *  after another (type -> hold -> delete -> next -> loop). Add, remove or
 *  reorder them; the FIRST one is what search engines and no-JS visitors
 *  see, and it stays the accessible label for the heading.
 * ──────────────────────────────────────────────────────────────────────── */
const TITLE_PHRASES = [
  'Terminal user interfaces for PHP',
  'Light and dark themes for terminal UIs',
  'Terminal UI widgets for every use case',
  'Build multi-lingual terminal UIs with ease',
  'Testable terminal UIs for your PHP projects',
];

const INSTALL_CMD = 'composer require drevops/tui';

const SUBHEAD =
  'A dependency-light PHP engine for interactive, keyboard-driven terminal ' +
  'forms. Declare the questions with a fluent builder; the engine renders a ' +
  'scrollable, themeable TUI - or collects the answers headlessly from JSON ' +
  'and environment variables.';

const FEATURES = [
  {idx: '01', name: 'Full-screen TUI', desc: <>A scrollable, keyboard-driven form; fields group into sections that drill in to any depth, with a contextual key-hint footer and a <code className={styles.tok}>?</code> help overlay.</>},
  {idx: '02', name: 'Inline editing', desc: <>A field's editor opens in place on the panel row, with its own view and keys; opt a field out to a full screen with <code className={styles.tok}>{'->standalone()'}</code>.</>},
  {idx: '03', name: 'Widget library', desc: <>Text, numbers, dates, single and multiple choice, fuzzy search, file browsing, reordering and gates.</>},
  {idx: '04', name: 'Builder-driven', desc: <>The form is declared in PHP with a fluent builder; the common cases need no extra code.</>},
  {idx: '05', name: 'Interactive or unattended', desc: <>Answer by keyboard, or supply the answers up front as a JSON payload and environment variables so it runs without prompting. Emits a JSON schema for agents.</>},
  {idx: '06', name: 'Derived values', desc: <>Compute one field from others with <code className={styles.tok}>str2name</code> transforms; chains settle to a fixpoint.</>},
  {idx: '07', name: 'Conditional fields', desc: <>Show or hide fields with <code className={styles.tok}>when</code> rules; a fix-up pass reconciles dependent answers.</>},
  {idx: '08', name: 'Themes', desc: <>The whole visual representation - colours, glyphs, layout - is a theme class; ships dark and light.</>},
  {idx: '09', name: 'Key bindings', desc: <>Remap navigation, edit, accept and cancel keys per widget type; ships a vim-style preset.</>},
];

const WIDGETS = [
  {name: 'Calendar', file: 'widget-calendar-dark-animated.svg', desc: <>A month calendar returning a normalized ISO <code className={styles.tok}>YYYY-MM-DD</code>; arrows move by day and week.</>},
  {name: 'Confirm', file: 'widget-confirm-dark-animated.svg', desc: <>Yes/No toggle; arrows or Space switch, <code className={styles.tok}>y</code>/<code className={styles.tok}>n</code> set the choice directly, Enter accepts.</>},
  {name: 'File picker', file: 'widget-filepicker-dark-animated.svg', desc: <>Browse the filesystem for a path, or several with <code className={styles.tok}>{'->multiple()'}</code>; <code className={styles.tok}>{'->'}</code> enters a directory, <code className={styles.tok}>{'<-'}</code> returns to its parent.</>},
  {name: 'Number', file: 'widget-number-dark-animated.svg', desc: <>Integer entry accepted as an <code className={styles.tok}>int</code>, with optional min, max and step.</>},
  {name: 'Password', file: 'widget-password-dark-animated.svg', desc: <>Text rendered as a mask everywhere; the accepted value stays plain for the consumer, and can be made revealable.</>},
  {name: 'Pause', file: 'widget-pause-dark-animated.svg', desc: <>An acknowledgement gate; Enter or Space accepts. Unattended runs auto-acknowledge it.</>},
  {name: 'Reorder', file: 'widget-reorder-dark-animated.svg', desc: <>Rank a list by moving items; Space picks an item up, arrows carry it, Enter accepts.</>},
  {name: 'Search', file: 'widget-search-dark-animated.svg', desc: <>Single choice with a visible filter line; typing fuzzy-matches and ranks the labels.</>},
  {name: 'Select', file: 'widget-select-dark-animated.svg', desc: <>Single choice from a list; arrows move, Enter accepts, long lists page around the cursor.</>},
  {name: 'Suggest', file: 'widget-suggest-dark-animated.svg', desc: <>Free text with autocomplete over a fixed option set; suggestions fuzzy-matched and ranked.</>},
  {name: 'Text', file: 'widget-text-dark-animated.svg', desc: <>Single-line input with a movable caret; type to insert, arrows move, Backspace deletes.</>},
  {name: 'Textarea', file: 'widget-textarea-dark-animated.svg', desc: <>Multi-line input; Enter inserts a newline, Tab accepts, with an external-editor handoff.</>},
  {name: 'Toggle', file: 'widget-toggle-dark-animated.svg', desc: <>An inline switch between two labelled values; arrows or Space flip, first letter sets it directly.</>},
];

const MODES = [
  {k: 'INTERACTIVE', t: 'On a terminal', d: <>A scrollable, keyboard-driven TUI; fields group into sections that drill in to any depth, with a contextual key-hint footer and a <code className={styles.tok}>?</code> help overlay.</>},
  {k: 'UNATTENDED', t: 'Everywhere else', d: <>Supply the answers up front as a JSON payload and environment variables so it runs without prompting. Emits a JSON schema for agents.</>},
];

/* Syntax-highlighted quick-start snippet, built as JSX tokens (no innerHTML).
 * Each token is {t: text} for plain text or {c: class-key, t: text} for a
 * highlighted span; lines are joined with newlines inside the <pre>. */
const CODE_LINES = [
  [{c: 'k', t: 'use'}, {t: ' '}, {c: 't', t: 'DrevOps\\Tui\\Builder\\Form'}, {c: 'p', t: ';'}],
  [{c: 'k', t: 'use'}, {t: ' '}, {c: 't', t: 'DrevOps\\Tui\\Builder\\PanelBuilder'}, {c: 'p', t: ';'}],
  [{c: 'k', t: 'use'}, {t: ' '}, {c: 't', t: 'DrevOps\\Tui\\Tui'}, {c: 'p', t: ';'}],
  [],
  [{c: 'v', t: '$form'}, {t: ' = '}, {c: 't', t: 'Form'}, {t: '::'}, {c: 'm', t: 'create'}, {t: '('}, {c: 's', t: "'New project'"}, {t: ')'}],
  [{t: '  ->'}, {c: 'm', t: 'panel'}, {t: '('}, {c: 's', t: "'setup'"}, {t: ', '}, {c: 's', t: "'Setup'"}, {t: ', '}, {c: 'k', t: 'function'}, {t: ' ('}, {c: 't', t: 'PanelBuilder'}, {t: ' '}, {c: 'v', t: '$p'}, {t: '): '}, {c: 'k', t: 'void'}, {t: ' {'}],
  [{t: '    '}, {c: 'v', t: '$p'}, {t: '->'}, {c: 'm', t: 'text'}, {t: '('}, {c: 's', t: "'name'"}, {t: ', '}, {c: 's', t: "'Project name'"}, {t: ')->'}, {c: 'm', t: 'required'}, {t: '()'}, {c: 'p', t: ';'}],
  [{t: '    '}, {c: 'v', t: '$p'}, {t: '->'}, {c: 'm', t: 'select'}, {t: '('}, {c: 's', t: "'type'"}, {t: ', '}, {c: 's', t: "'Project type'"}, {t: ')->'}, {c: 'm', t: 'options'}, {t: '(['}],
  [{t: '      '}, {c: 's', t: "'app'"}, {t: ' => '}, {c: 's', t: "'Application'"}, {t: ','}],
  [{t: '      '}, {c: 's', t: "'library'"}, {t: ' => '}, {c: 's', t: "'Library'"}, {t: ','}],
  [{t: '    ])'}, {c: 'p', t: ';'}],
  [{t: '    '}, {c: 'v', t: '$p'}, {t: '->'}, {c: 'm', t: 'select'}, {t: '('}, {c: 's', t: "'services'"}, {t: ', '}, {c: 's', t: "'Services'"}, {t: ')->'}, {c: 'm', t: 'multiple'}, {t: '()->'}, {c: 'm', t: 'default'}, {t: '(['}, {c: 's', t: "'redis'"}, {t: '])->'}, {c: 'm', t: 'options'}, {t: '(['}],
  [{t: '      '}, {c: 's', t: "'redis'"}, {t: ' => '}, {c: 's', t: "'Redis'"}, {t: ','}],
  [{t: '      '}, {c: 's', t: "'solr'"}, {t: ' => '}, {c: 's', t: "'Solr'"}, {t: ','}],
  [{t: '      '}, {c: 's', t: "'clamav'"}, {t: ' => '}, {c: 's', t: "'ClamAV'"}, {t: ','}],
  [{t: '    ])'}, {c: 'p', t: ';'}],
  [{t: '    '}, {c: 'v', t: '$p'}, {t: '->'}, {c: 'm', t: 'confirm'}, {t: '('}, {c: 's', t: "'ci'"}, {t: ', '}, {c: 's', t: "'Set up CI?'"}, {t: ')->'}, {c: 'm', t: 'default'}, {t: '('}, {c: 'k', t: 'TRUE'}, {t: ')'}, {c: 'p', t: ';'}],
  [{t: '  })'}, {c: 'p', t: ';'}],
  [],
  [{c: 'v', t: '$tui'}, {t: ' = '}, {c: 'k', t: 'new'}, {t: ' '}, {c: 't', t: 'Tui'}, {t: '('}, {c: 'v', t: '$form'}, {t: ', ['}, {c: 's', t: "'App\\\\Handler'"}, {t: '])'}, {c: 'p', t: ';'}],
  [],
  [{c: 'c', t: '// Interactive on a terminal, non-interactive otherwise.'}],
  [{c: 'v', t: '$answers'}, {t: ' = '}, {c: 'v', t: '$tui'}, {t: '->'}, {c: 'm', t: 'run'}, {t: '()'}, {c: 'p', t: ';'}],
];

function QuickStartCode() {
  return (
    <pre className={styles.code}><code>{CODE_LINES.map((line, li) => (
      <React.Fragment key={li}>
        {li > 0 ? '\n' : null}
        {line.map((tok, ti) => (tok.c ? <span key={ti} className={styles[tok.c]}>{tok.t}</span> : <React.Fragment key={ti}>{tok.t}</React.Fragment>))}
      </React.Fragment>
    ))}</code></pre>
  );
}

const GITHUB_PATH = 'M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.01 8.01 0 0016 8c0-4.42-3.58-8-8-8z';

function useTypewriter(phrases) {
  const [text, setText] = useState(phrases[0]);

  useEffect(() => {
    const reduce = typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduce) {
      setText(phrases[0]);
      return undefined;
    }

    let pi = 0;
    let ci = 0;
    let deleting = false;
    let timer;

    setText('');

    const tick = () => {
      const word = phrases[pi];

      if (!deleting) {
        ci += 1;
        setText(word.slice(0, ci));

        if (ci === word.length) {
          deleting = true;
          timer = setTimeout(tick, 2000);
          return;
        }

        timer = setTimeout(tick, 46 + Math.random() * 44);
        return;
      }

      ci -= 1;
      setText(word.slice(0, ci));

      if (ci === 0) {
        deleting = false;
        pi = (pi + 1) % phrases.length;
        timer = setTimeout(tick, 380);
        return;
      }

      timer = setTimeout(tick, 26 + Math.random() * 22);
    };

    timer = setTimeout(tick, 460);

    return () => clearTimeout(timer);
  }, [phrases]);

  return text;
}

function CopyButton({text}) {
  const [done, setDone] = useState(false);

  const onCopy = () => {
    const flash = () => {
      setDone(true);
      setTimeout(() => setDone(false), 1600);
    };

    if (typeof navigator !== 'undefined' && navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(flash, flash);
      return;
    }

    flash();
  };

  return (
    <button type="button" className={clsx(styles.copy, done && styles.copyDone)} onClick={onCopy} aria-label="Copy the install command to the clipboard">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
        <rect x="9" y="9" width="11" height="11" rx="2" />
        <path d="M5 15V5a2 2 0 0 1 2-2h10" />
      </svg>
      <span>{done ? 'copied' : 'copy'}</span>
    </button>
  );
}

export default function Home() {
  const rootRef = useRef(null);
  const typed = useTypewriter(TITLE_PHRASES);
  const {withBaseUrl} = useBaseUrlUtils();

  useEffect(() => {
    const root = rootRef.current;

    if (!root) {
      return undefined;
    }

    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const items = root.querySelectorAll('.' + styles.revealUp);

    if (reduce || typeof IntersectionObserver === 'undefined') {
      items.forEach((el) => el.classList.add(styles.shown));
      return undefined;
    }

    root.classList.add(styles.revealReady);

    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add(styles.shown);
          io.unobserve(entry.target);
        }
      });
    }, {rootMargin: '0px 0px -8% 0px', threshold: 0.08});

    items.forEach((el) => io.observe(el));

    return () => io.disconnect();
  }, []);

  return (
    <Layout title={TITLE_PHRASES[0]} description={SUBHEAD}>
      <Head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,400;0,500;0,700;0,800;1,400&family=Martian+Mono:wght@600;700;800&display=swap" rel="stylesheet" />
      </Head>

      <div className={styles.home} ref={rootRef}>
        <div className={styles.grain} aria-hidden="true" />
        <div className={styles.scan} aria-hidden="true" />

        <div className={styles.content}>
          {/* hero */}
          <section className={clsx(styles.section, styles.hero)} aria-labelledby="hero-title">
            <div className={styles.wrap}>
              <span className={clsx(styles.eyebrow, styles.loadReveal)} style={{'--d': '.16s'}}>
                <span className={styles.pulse} aria-hidden="true" />A PHP library &middot; MIT
              </span>

              <h1 id="hero-title" className={clsx(styles.headline, styles.loadReveal)} style={{'--d': '.24s'}} aria-label={TITLE_PHRASES[0]}>
                <span aria-hidden="true">{typed}</span>
                <span className={styles.cursor} aria-hidden="true" />
              </h1>

              <p className={clsx(styles.subhead, styles.loadReveal)} style={{'--d': '.38s'}}>{SUBHEAD}</p>

              <div className={clsx(styles.install, styles.loadReveal)} style={{'--d': '.5s'}}>
                <code className={styles.installCmd}><span className={styles.prompt} aria-hidden="true">$</span> {INSTALL_CMD}</code>
                <CopyButton text={INSTALL_CMD} />
              </div>

              <div className={clsx(styles.ctaRow, styles.loadReveal)} style={{'--d': '.6s'}}>
                <a className={clsx(styles.btn, styles.btnPrimary)} href={withBaseUrl('/installation')}>Get started</a>
                <a className={clsx(styles.btn, styles.btnGhost)} href="https://github.com/drevops/tui">
                  <svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" fill="currentColor"><path d={GITHUB_PATH} /></svg>
                  View on GitHub
                </a>
                <a className={clsx(styles.btn, styles.btnLink)} href={withBaseUrl('/introduction')}>Read the docs &rarr;</a>
              </div>

              <div className={clsx(styles.term, styles.loadReveal)} style={{'--d': '.72s'}} role="img" aria-label="A form built with TUI, recorded in the terminal">
                <div className={styles.termBar}>
                  <span className={styles.dots} aria-hidden="true"><span /><span /><span /></span>
                  <span className={styles.termTitle}>tui - zsh</span>
                  <span className={styles.termMeta} aria-hidden="true">UTF-8</span>
                </div>
                <div className={styles.termScreen}>
                  <img src={withBaseUrl('/bordered-panels-dark-animated.svg')} alt="Animated terminal recording of a keyboard-driven form built with TUI, shown inside a rounded border." width="800" height="441" loading="eager" decoding="async" />
                </div>
              </div>
            </div>
          </section>

          {/* quick start */}
          <section id="quickstart" className={styles.section} aria-labelledby="qs-title">
            <div className={styles.wrap}>
              <div className={styles.qsHead}>
                <span className={clsx(styles.kicker, styles.revealUp)}>quick-start</span>
                <h2 id="qs-title" className={clsx(styles.h2, styles.revealUp)} style={{'--i': 1}}>Declare a form in PHP</h2>
                <p className={clsx(styles.lead, styles.revealUp)} style={{'--i': 2}}>Describe the questions with a fluent builder - text, choices, toggles and more - add a handler class where a question needs real behaviour, and call <code className={styles.tok}>run()</code>. Interactive on a terminal, non-interactive otherwise.</p>
              </div>

              <div className={clsx(styles.codewin, styles.revealUp)} style={{'--i': 1}}>
                <div className={styles.codewinBar}>
                  <span className={styles.dots} aria-hidden="true"><span /><span /><span /></span>
                  <span className={styles.codewinFile}>form.php</span>
                </div>
                <div className={styles.codewinScroll}>
                  <QuickStartCode />
                </div>
              </div>

              <div className={styles.modes}>
                {MODES.map((mode, i) => (
                  <div key={mode.k} className={clsx(styles.mode, styles.revealUp)} style={{'--i': i}}>
                    <span className={styles.modeK}><span className={styles.prompt} aria-hidden="true">$</span>{mode.k}</span>
                    <p className={styles.modeT}>{mode.t}</p>
                    <p className={styles.modeD}>{mode.d}</p>
                  </div>
                ))}
              </div>
            </div>
          </section>

          {/* features */}
          <section id="features" className={styles.section} aria-labelledby="features-title">
            <div className={styles.wrap}>
              <div className={styles.featuresHead}>
                <span className={clsx(styles.kicker, styles.revealUp)}>features</span>
                <h2 id="features-title" className={clsx(styles.h2, styles.revealUp)} style={{'--i': 1}}>Built for keyboard-driven forms</h2>
                <p className={clsx(styles.lead, styles.revealUp)} style={{'--i': 2}}>The engine stays generic. Your questions and handlers live in the consumer - it collects, you apply.</p>
              </div>
              <div className={styles.featGrid}>
                {FEATURES.map((feature, i) => (
                  <article key={feature.name} className={clsx(styles.feat, styles.revealUp)} style={{'--i': i}}>
                    <span className={styles.featIdx}>{feature.idx}</span>
                    <strong className={styles.featName}>{feature.name}</strong>
                    <p className={styles.featDesc}>{feature.desc}</p>
                  </article>
                ))}
              </div>
            </div>
          </section>

          {/* widgets */}
          <section id="widgets" className={styles.section} aria-labelledby="widgets-title">
            <div className={styles.wrap}>
              <div className={styles.widgetsHead}>
                <span className={clsx(styles.kicker, styles.revealUp)}>widgets</span>
                <h2 id="widgets-title" className={clsx(styles.h2, styles.revealUp)} style={{'--i': 1}}>One layer, every field type</h2>
                <p className={clsx(styles.lead, styles.revealUp)} style={{'--i': 2}}>Text, numbers, dates, single and multiple choice, fuzzy search, file browsing, reordering and gates.</p>
              </div>
              <div className={styles.wgrid}>
                {WIDGETS.map((widget) => (
                  <article key={widget.name} className={clsx(styles.wcard, styles.revealUp)}>
                    <div className={styles.wcardScreen}>
                      <img src={withBaseUrl('/' + widget.file)} alt={`Animated demo of the ${widget.name} widget`} loading="lazy" decoding="async" />
                    </div>
                    <div className={styles.wcardMeta}>
                      <h3 className={styles.wcardName}>{widget.name}</h3>
                      <p className={styles.wcardDesc}>{widget.desc}</p>
                    </div>
                  </article>
                ))}
              </div>
            </div>
          </section>
        </div>
      </div>
    </Layout>
  );
}
