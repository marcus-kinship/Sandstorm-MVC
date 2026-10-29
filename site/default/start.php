<?php
$e = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$docs = 'https://github.com/marcus-kinship/Sandstorm-MVC';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Sandstorm-MVC</title>
    <style>
        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background-color: #1d3f7a;
            background-image: url(<?php print include $_SERVER['HTTP_path_sys'] . 'Views/background.php'; ?>);
            background-repeat: no-repeat;
            background-size: cover;
            background-attachment: fixed;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #ffffff;
        }

        .preload {
            position: fixed;
            inset: 0;
            background-color: #000000;
            z-index: 9999;
            transition: opacity 1s ease;
        }

        .preload.fadeout {
            opacity: 0;
        }

        .row {
            display: flex;
            justify-content: center;
            padding: 0 16px;
        }

        .window {
            width: 888px;
            max-width: 100%;
            margin: 94px 0 50px;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            background: linear-gradient(144deg, rgba(37, 37, 37, 0.3) 0%, rgb(10 10 10 / 20%) 47%);
            box-shadow: 1px 1px 1px #ffffff29, -1px -1px 1px #ffffff29;
            border-radius: 20px;
            overflow: hidden;
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 1s ease, transform 1s ease;
        }

        .window.loaded {
            opacity: 1;
            transform: translateY(0);
        }

        .title {
            height: 76px;
            margin: 0 43px;
            font: 19px sans-serif;
            /* After font: the font shorthand resets line-height */
            line-height: 76px;
            color: rgb(255 255 255 / 96%);
            text-shadow: 1px 1px 1px #000000;
        }

        .content {
            padding: 0 43px 43px;
            opacity: 0;
            transition: opacity 1s ease;
        }

        .content.loaded {
            opacity: 1;
        }

        h1 {
            margin: 0;
            font: 27px sans-serif;
            text-shadow: 1px 1px 1px #000000;
        }

        h2 {
            margin: 0 0 8px;
            font: 18px sans-serif;
            text-shadow: 1px 1px 1px #000000;
        }

        p {
            margin: 18px 0 0;
            font-size: 16px;
            line-height: 22px;
            text-shadow: 1px 1px 1px #000000;
        }

        code {
            padding: 1px 6px;
            border-radius: 4px;
            background-color: rgb(0 0 0 / 30%);
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .card {
            margin-top: 28px;
            padding: 18px 22px;
            border-radius: 14px;
            background-color: rgb(0 0 0 / 25%);
            box-shadow: inset 0 0 0 1px rgb(255 255 255 / 10%);
            border-left: 6px solid #9e9e9e;
        }

        .card p {
            margin-top: 6px;
        }

        .card.ok {
            border-left-color: #4caf50;
        }

        .card.fail {
            border-left-color: #e5533d;
        }

        .dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            margin-right: 8px;
            border-radius: 50%;
            vertical-align: 1px;
        }

        .ok .dot {
            background-color: #4caf50;
            box-shadow: 0 0 8px #4caf50;
        }

        .fail .dot {
            background-color: #e5533d;
            box-shadow: 0 0 8px #e5533d;
        }

        .docs {
            margin-top: 32px;
        }

        .button-container {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 14px;
        }

        .aero-button {
            position: relative;
            display: inline-block;
            padding: 9px 20px;
            border-radius: 28px;
            overflow: hidden;
            color: #ffffff;
            font: 13px system-ui, sans-serif;
            text-decoration: none;
            text-shadow: 1px 1px 1px #000000;
            background: linear-gradient(to top,
                    rgb(53 53 53 / 50%) 1%,
                    rgb(53 53 53 / 50%) 12%,
                    rgb(0 0 0 / 50%) 33%,
                    rgb(0 0 0 / 50%) 50%,
                    rgb(39 39 39 / 50%) 51%,
                    rgb(104 104 104 / 50%) 100%);
            box-shadow: inset 0 0 1px 1px rgb(255 255 255 / 12%);
            isolation: isolate;
        }

        /* Warm glow under the button, follows the mouse */
        .aero-button::after {
            content: "";
            position: absolute;
            left: var(--x, 50%);
            top: var(--y, 130%);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            filter: blur(8px);
            background: radial-gradient(circle, #ffc107 0%, #ffb300 100%);
            transition: top 0.5s ease, left 0.5s ease;
            animation: pulse 3s infinite;
            z-index: -1;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0;
            }

            100% {
                opacity: 1;
            }
        }

        .aero-button:hover::after,
        .aero-button:focus-visible::after {
            top: var(--y, 50%);
        }

        @media (max-width: 625px) {
            .window {
                margin: 10px 0;
            }

            .title {
                margin: 0 21px;
            }

            .content {
                padding: 0 21px 21px;
            }
        }

        @media (prefers-reduced-motion: reduce) {

            .window,
            .content,
            .preload {
                transition: none;
            }

            .aero-button::after {
                animation: none;
            }
        }
    </style>
</head>

<body>
    <div class="preload"></div>
    <div class="row">
        <div class="window">
            <div class="title">Sandstorm - Welcome</div>
            <div class="content">
                <h1>Welcome to Sandstorm-MVC</h1>
                <p>Sandstorm is running. This page is the view <code>site/default/start.php</code>, loaded by
                    <code>DefaultController::index()</code> in <code>site/default/Default.class.php</code>.</p>

                <?php if ($mysql['connected']): ?>
                    <section class="card ok">
                        <h2><span class="dot"></span>MySQL: connected</h2>
                        <p>Database <code><?= $e($mysql['name']) ?></code> on <code><?= $e($mysql['host']) ?></code>
                            as <code><?= $e($mysql['user']) ?></code>.</p>
                    </section>
                <?php else: ?>
                    <section class="card fail">
                        <h2><span class="dot"></span>MySQL: not connected</h2>
                        <p>Database <code><?= $e($mysql['name']) ?></code> on <code><?= $e($mysql['host']) ?></code>
                            as <code><?= $e($mysql['user']) ?></code>.</p>
                        <p><?= $e($mysql['error']) ?></p>
                        <p>Fill in your database details in <code>app/config/mysql/default.db.php</code> and reload this page.
                            The database is created automatically if it does not exist.</p>
                    </section>
                <?php endif ?>

                <div class="docs">
                    <h2>Documentation</h2>
                    <div class="button-container">
                        <a class="aero-button" href="<?= $docs ?>#readme">README</a>
                        <a class="aero-button" href="<?= $docs ?>/blob/main/help/Controllers/controllers.md">Controllers</a>
                        <a class="aero-button" href="<?= $docs ?>/blob/main/help/Request%20Handling%20Documentation/Request%20Handling%20Documentation.md">Request handling</a>
                        <a class="aero-button" href="<?= $docs ?>/tree/main/help">All help pages</a>
                    </div>
                </div>

                <p>Create your first page by adding a folder in <code>site/</code> with a controller and a view.</p>
            </div>
        </div>
    </div>

    <script>
        // Same entrance as the error page: fade from black, then the window, then the content
        (function () {
            var preload = document.querySelector('.preload');
            var win = document.querySelector('.window');
            var content = document.querySelector('.content');
            var image = new Image();

            image.onload = image.onerror = function () {
                preload.classList.add('fadeout');
                setTimeout(function () {
                    preload.style.display = 'none';
                    win.classList.add('loaded');
                    setTimeout(function () { content.classList.add('loaded'); }, 600);
                }, 600);
            };
            image.src = getComputedStyle(document.body).backgroundImage.slice(4, -1).replace(/"/g, '');

            // Glow follows the mouse inside the buttons
            document.querySelectorAll('.aero-button').forEach(function (button) {
                button.addEventListener('mousemove', function (event) {
                    var box = button.getBoundingClientRect();
                    button.style.setProperty('--x', (event.clientX - box.left) + 'px');
                    button.style.setProperty('--y', (event.clientY - box.top) + 'px');
                });
                button.addEventListener('mouseleave', function () {
                    button.style.removeProperty('--x');
                    button.style.removeProperty('--y');
                });
            });
        })();
    </script>
</body>

</html>
