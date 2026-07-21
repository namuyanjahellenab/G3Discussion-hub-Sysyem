import './bootstrap';

// Bundled locally instead of the cdn.jsdelivr.net script every page loaded -
// see app.css for why (same external-CDN-latency issue, same fix).
import 'bootstrap/dist/js/bootstrap.bundle.min.js';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
