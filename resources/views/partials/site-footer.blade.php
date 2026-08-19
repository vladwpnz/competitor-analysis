<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-identity">
            <a href="{{ route('home') }}" class="brand footer-brand">
                <span class="brand-mark" aria-hidden="true">
                    <i></i><i></i><i></i><i></i>
                </span>
                <span class="brand-name">Account Intelligence</span>
            </a>

            <p>Evidence-led lookalike account discovery for local and broader markets.</p>
        </div>

        <div class="footer-product-note">
            <span>Website signals, Google Places and optional Gemini</span>
            <span>Independent portfolio project</span>
        </div>
    </div>

    <div class="container footer-bottom">
        <span>&copy; {{ now()->year }} Account Intelligence</span>
        <a href="{{ route('home') }}#analysis-form">Start a new analysis</a>
    </div>
</footer>
