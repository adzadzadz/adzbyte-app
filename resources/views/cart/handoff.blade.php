<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Opening your cart · Adzbyte</title>
    <style nonce="{{ $nonce }}">body{margin:0;background:#0a0f17;color:#f8fafc;font:1rem/1.6 system-ui,sans-serif}main{max-width:36rem;margin:12vh auto;padding:1.5rem;overflow-wrap:anywhere}h1{font-size:1.8rem}a{color:#c4b5fd}a:focus-visible{outline:2px solid #c4b5fd;outline-offset:4px}[hidden]{display:none}</style>
</head>
<body>
    <main>
        <p>ADZBYTE · YOUR CART</p>
        <h1>Opening your cart</h1>
        <p id="cart-progress" role="status">Please wait a moment.</p>
        <div id="cart-error" role="alert" hidden>
            <p>This cart link has expired or was already used. Return to the website and select View Cart again. Your saved items have not expired.</p>
            <a href="{{ $shop }}">Return to services</a>
        </div>
        <noscript>JavaScript is needed to open this cart link. Enable it, then return to the website and select View Cart again.</noscript>
    </main>
    <script nonce="{{ $nonce }}">
        (() => {
            const token = window.location.hash.slice(1);
            window.history.replaceState(null, '', '/cart/handoff');
            const fail = () => {
                document.getElementById('cart-progress').hidden = true;
                document.getElementById('cart-error').hidden = false;
            };
            if (!/^[a-f0-9]{64}$/.test(token)) { fail(); return; }
            fetch('/api/v1/cart-handoffs/consume', {
                method: 'POST', credentials: 'same-origin', redirect: 'error',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify({token})
            }).then(async response => {
                if (!response.ok) throw new Error('Cart link unavailable');
                const result = await response.json();
                if (result?.data?.redirect !== '/cart') throw new Error('Invalid destination');
                window.location.replace('/cart');
            }).catch(fail);
        })();
    </script>
</body>
</html>
