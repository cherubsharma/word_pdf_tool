<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title>Word to PDF Converter — DocuLite</title>
    <meta name="description" content="Convert Word documents to PDF online with a simple, easy-to-use tool.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        <script src="{{ asset('js/app.js') }}" defer></script>
    @endif
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="#home" aria-label="DocuLite home">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M7 3.75h7l4.25 4.5v12H7a2 2 0 0 1-2-2v-12a2.5 2.5 0 0 1 2-2.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                        <path d="M14 4v4.5h4M8.5 13h6M8.5 16.5h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span>doculite</span>
            </a>
            <nav class="main-nav" aria-label="Main navigation">
                <a href="#tools">Tools</a>
                <a href="#pricing">Pricing</a>
                <a href="#about">About</a>
            </nav>
            <a class="header-link" href="#tools">Get started <span aria-hidden="true">↗</span></a>
        </div>
    </header>

    <main id="home">
        <section class="hero" id="tools">
            <div class="hero-content">
                <div class="eyebrow"><span class="eyebrow-dot"></span> SIMPLE. SECURE. ONLINE.</div>
                <h1>Convert Word to<br class="desktop-break"> <span>PDF Online.</span></h1>
                <p class="hero-copy">Make your documents easy to share. Turn a Word file into a polished PDF in just a few clicks.</p>

                <div class="upload-card">
                    <div class="drop-zone" id="drop-zone" tabindex="0" role="button" aria-label="Choose a Word file (.doc or .docx) or drop it here">
                        <input class="file-input" id="file-input" type="file" accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" aria-label="Select a DOC or DOCX file">
                        <div class="upload-icon" aria-hidden="true">
                            <svg viewBox="0 0 48 48" fill="none">
                                <path d="M13 5.5h15l9 9v24a4 4 0 0 1-4 4H13a4 4 0 0 1-4-4v-29a4 4 0 0 1 4-4Z" fill="white" stroke="currentColor" stroke-width="2"/>
                                <path d="M28 6v9h9M16 25h18M16 31h18M16 37h10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M24 18v-8m0 0-3.5 3.5M24 10l3.5 3.5" stroke="#3478F6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h2>Drop your Word file here</h2>
                        <p class="drop-hint">or choose a file from your device</p>
                        <button class="choose-button" id="choose-button" type="button">
                            <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="M10 13V3m0 0L6.5 6.5M10 3l3.5 3.5M4 12.5v3A1.5 1.5 0 0 0 5.5 17h9a1.5 1.5 0 0 0 1.5-1.5v-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Select DOCX file
                        </button>
                        <p class="file-note">Supports DOC &amp; DOCX files up to 25MB <span aria-hidden="true">·</span> Preserves layout &amp; formatting</p>
                    </div>

                    <!-- Selected file state -->
                    <div class="action-card hidden" id="selected-card">
                        <div class="file-preview-row">
                            <div class="file-preview-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M7 3.75h7l4.25 4.5v12H7a2 2 0 0 1-2-2v-12a2.5 2.5 0 0 1 2-2.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 4v4.5h4M8.5 13h6M8.5 16.5h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </div>
                            <div class="file-preview-details">
                                <span class="file-name" id="selected-filename">document.docx</span>
                                <span class="file-size" id="selected-filesize">0 KB</span>
                            </div>
                        </div>
                        <div class="action-buttons-group">
                            <button class="convert-button" id="convert-button" type="button">
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M4 10h12m0 0l-5-5m5 5l-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Convert to PDF
                            </button>
                            <button class="text-cancel-button" id="change-file-button" type="button">Choose another file</button>
                        </div>
                    </div>

                    <!-- Processing / converting state -->
                    <div class="status-card hidden" id="converting-card">
                        <div class="spinner-ring" aria-hidden="true"></div>
                        <h3>Converting your document...</h3>
                        <p class="status-subtext">Converting Word to high-fidelity PDF. This usually takes just a few seconds.</p>
                    </div>

                    <!-- Success / Download state -->
                    <div class="status-card success-card hidden" id="success-card">
                        <div class="status-icon-circle green" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <h3>Conversion Complete!</h3>
                        <p class="status-subtext" id="success-filename">Your PDF has been generated.</p>
                        <div class="action-buttons-group">
                            <a class="download-button" id="download-link" href="#" download>
                                <svg viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M10 3v10m0 0l3.5-3.5M10 13L6.5 9.5M4 16.5h12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Download PDF
                            </a>
                            <button class="text-cancel-button" id="convert-another-button" type="button">Convert another document</button>
                        </div>
                    </div>

                    <!-- Error state -->
                    <div class="status-card error-card hidden" id="error-card">
                        <div class="status-icon-circle red" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M12 8v5m0 3.5h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <h3 id="error-title">Conversion Failed</h3>
                        <p class="error-message" id="error-message">An error occurred during conversion.</p>
                        <div class="instructions-box hidden" id="error-instructions">
                            <strong>Setup Required on Server:</strong>
                            <ol id="instructions-list"></ol>
                        </div>
                        <div class="action-buttons-group">
                            <button class="choose-button" id="try-again-button" type="button">Try Again</button>
                        </div>
                    </div>

                    <p class="file-status sr-only" id="file-status" aria-live="polite"></p>
                    <div class="privacy-note">
                        <svg viewBox="0 0 18 18" fill="none" aria-hidden="true">
                            <path d="M9 2.25 14.25 4v4.2c0 3.4-2.25 5.75-5.25 7.55C6 13.95 3.75 11.6 3.75 8.2V4L9 2.25Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                            <path d="m6.75 8.75 1.5 1.5 3-3.25" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Your files stay yours. Uploaded files are converted temporarily and deleted immediately.
                    </div>
                </div>
                <div class="trust-line">
                    <span class="trust-check" aria-hidden="true">✓</span> No software to install
                    <span class="trust-divider" aria-hidden="true"></span>
                    <span class="trust-check" aria-hidden="true">✓</span> Works on any device
                </div>
            </div>
            <div class="hero-art" aria-hidden="true">
                <div class="art-orbit orbit-one"></div>
                <div class="art-orbit orbit-two"></div>
                <div class="doc-card doc-word">
                    <div class="doc-fold"></div>
                    <div class="word-label">W</div>
                    <span class="doc-lines"></span><span class="doc-lines short"></span><span class="doc-lines"></span><span class="doc-lines shorter"></span>
                    <span class="doc-caption">WORD DOCUMENT</span>
                </div>
                <div class="convert-arrow"><span>→</span></div>
                <div class="doc-card doc-pdf">
                    <div class="doc-fold"></div>
                    <div class="pdf-label">PDF</div>
                    <span class="doc-lines"></span><span class="doc-lines short"></span><span class="doc-lines"></span><span class="doc-lines shorter"></span>
                    <span class="doc-caption">READY TO SHARE</span>
                </div>
                <div class="art-spark spark-one">✦</div>
                <div class="art-spark spark-two">✧</div>
            </div>
        </section>

        <section class="how-section" id="about">
            <div class="section-heading">
                <span class="section-kicker">HOW IT WORKS</span>
                <h2>Documents, made simple.</h2>
                <p>Getting from Word to PDF should be the easiest part of your day.</p>
            </div>
            <div class="steps">
                <article class="step-card">
                    <span class="step-number">01</span>
                    <div class="step-icon blue-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M12 15V4m0 0L8 8m4-4 4 4M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <h3>Upload your file</h3>
                    <p>Choose a DOCX document from your device or drag it into the upload area.</p>
                </article>
                <article class="step-card">
                    <span class="step-number">02</span>
                    <div class="step-icon violet-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M7 4.5h7l4 4v11H7a2 2 0 0 1-2-2v-11a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 5v4h4M8.5 13h7M8.5 16.5h7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <h3>Convert to PDF</h3>
                    <p>Your document is prepared as a PDF while keeping its layout and formatting intact.</p>
                </article>
                <article class="step-card">
                    <span class="step-number">03</span>
                    <div class="step-icon green-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M12 4v11m0 0 4-4m-4 4-4-4M5 16v3a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                    <h3>Download &amp; share</h3>
                    <p>Save your finished PDF and share it with anyone, on any device.</p>
                </article>
            </div>
        </section>

        <section class="bottom-cta" id="pricing">
            <div>
                <span class="section-kicker">A BETTER WAY TO SHARE</span>
                <h2>Your next PDF is just around the corner.</h2>
            </div>
            <a class="cta-link" href="#tools">Choose a Word file <span aria-hidden="true">↑</span></a>
        </section>
    </main>

    <footer class="site-footer">
        <a class="brand footer-brand" href="#home">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none"><path d="M7 3.75h7l4.25 4.5v12H7a2 2 0 0 1-2-2v-12a2.5 2.5 0 0 1 2-2.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M14 4v4.5h4M8.5 13h6M8.5 16.5h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span>doculite</span>
        </a>
        <p>Simple tools for documents that go places.</p>
        <span class="copyright">© {{ date('Y') }} DocuLite. All rights reserved.</span>
    </footer>
</body>
</html>
