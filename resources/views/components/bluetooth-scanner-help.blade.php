<div class="bluetooth-help" data-bluetooth-help data-target-id="{{ $targetId }}">
    <button class="button button-secondary" type="button" aria-expanded="false" aria-controls="bluetooth-help-{{ $targetId }}" data-bluetooth-open>
        Connect Phone
    </button>
    <section id="bluetooth-help-{{ $targetId }}" class="bluetooth-help-panel" hidden>
        <h3>Use a phone as a Bluetooth scanner</h3>
        <ol>
            <li>Pair the phone with this computer in the computer's Bluetooth settings.</li>
            <li>On the phone, use a scanner app that supports Bluetooth HID / keyboard mode and enable it.</li>
            <li>Return here, focus the scanner field, then scan. The phone must send Enter after each code.</li>
        </ol>
        <p>This page cannot pair Bluetooth devices itself. It receives scans as keyboard input after the operating system has paired the phone.</p>
        <div class="row-actions">
            <button class="button" type="button" data-bluetooth-focus>Focus Scanner</button>
            <button class="button button-secondary" type="button" data-bluetooth-close>Close</button>
        </div>
    </section>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-bluetooth-help]').forEach(root => {
            const openButton = root.querySelector('[data-bluetooth-open]');
            const panel = root.querySelector('.bluetooth-help-panel');

            function closePanel() {
                panel.hidden = true;
                openButton.setAttribute('aria-expanded', 'false');
            }

            openButton.addEventListener('click', () => {
                panel.hidden = !panel.hidden;
                openButton.setAttribute('aria-expanded', String(!panel.hidden));
            });
            root.querySelector('[data-bluetooth-close]').addEventListener('click', closePanel);
            root.querySelector('[data-bluetooth-focus]').addEventListener('click', () => {
                closePanel();
                document.getElementById(root.dataset.targetId).focus();
            });
        });
    });
</script>
<style>
    .bluetooth-help { position:relative; }
    .bluetooth-help-panel { background:#fff; border:1px solid #99f6e4; border-radius:8px; box-shadow:0 8px 24px #0f172a24; left:0; max-width:min(360px,calc(100vw - 32px)); padding:16px; position:absolute; top:calc(100% + 8px); width:360px; z-index:30; }
    .bluetooth-help-panel h3 { margin:0 0 8px; }
    .bluetooth-help-panel ol { padding-left:22px; }
    @media(max-width:640px) { .bluetooth-help-panel { left:auto; right:0; width:min(360px,calc(100vw - 32px)); } }
</style>
