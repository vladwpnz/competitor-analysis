<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-identity">
            <a href="{{ route('home') }}" class="brand footer-brand">
                <span class="brand-mark" aria-hidden="true">
                    <i></i><i></i><i></i><i></i>
                </span>
                <span class="brand-name">Competitor Intelligence</span>
            </a>

            <p>Evidence-led competitor discovery for local and broader markets.</p>
        </div>

        <div class="footer-product-note">
            <span>Built with Laravel, Google Places and Gemini</span>
            <span>Independent portfolio project</span>
        </div>
    </div>

    <div class="container footer-bottom">
        <span>&copy; {{ now()->year }} Competitor Intelligence</span>
        <a href="{{ route('home') }}#analysis-form">Start a new analysis</a>
    </div>
</footer>
