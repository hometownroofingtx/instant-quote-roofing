# Review note: HTR Roof Quote update

This branch contains the three-step quote interface, submission feedback, editable report layouts, header/footer branding controls, and PDF pagination improvements through version 1.6.9. It also includes the existing security-review summary commit.

The original contact-save workflow, pricing calculations, measurement integrations and report data structures are preserved. Browser-reported GHL completion remains distinct from independent webhook verification.

Local PHP and affected Chrome browser/PDF suites passed before the implementation commit. Real Google/GHL/n8n submissions, email delivery, authenticated WordPress editing and native mobile printing still require live verification.

Scope: HTR Instant Roof Report plugin only. Feature Testimonials, theme files and workspace reports are excluded. This branch is for review; remote main is unchanged and no production deployment has been performed.

See README.md, docs/three-step-quote-redesign.md and docs/report-layout-editor.md for setup, validation limits and deployment/rollback notes.