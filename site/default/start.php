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
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f6f1e7;
            color: #3b3127;
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        main {
            max-width: 36rem;
            padding: 2rem 1.5rem;
        }

        h1 {
            margin: 0 0 1rem;
            font-size: 2.25rem;
        }

        h2 {
            margin: 0 0 0.5rem;
            font-size: 1.1rem;
        }

        code {
            background: #e9dfcc;
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
            overflow-wrap: anywhere;
        }

        a {
            color: #8a4b12;
        }

        .card {
            margin: 1.5rem 0;
            padding: 1rem 1.25rem;
            background: #fffaf0;
            border: 1px solid #e2d5bd;
            border-left: 6px solid #b9a88a;
            border-radius: 8px;
        }

        .card.ok {
            border-left-color: #3f8f4f;
        }

        .card.fail {
            border-left-color: #b8432f;
        }

        .card p {
            margin: 0.35rem 0;
        }

        ul {
            padding-left: 1.25rem;
        }
    </style>
</head>

<body>
    <main>
        <h1>Welcome to Sandstorm-MVC</h1>
        <p>Sandstorm is running. This page is the view <code>site/default/start.php</code>, loaded by
            <code>DefaultController::index()</code> in <code>site/default/Default.class.php</code>.</p>

        <?php if ($mysql['connected']): ?>
            <section class="card ok">
                <h2>MySQL: connected</h2>
                <p>Database <code><?= $e($mysql['name']) ?></code> on <code><?= $e($mysql['host']) ?></code>
                    as <code><?= $e($mysql['user']) ?></code>.</p>
            </section>
        <?php else: ?>
            <section class="card fail">
                <h2>MySQL: not connected</h2>
                <p>Database <code><?= $e($mysql['name']) ?></code> on <code><?= $e($mysql['host']) ?></code>
                    as <code><?= $e($mysql['user']) ?></code>.</p>
                <p><?= $e($mysql['error']) ?></p>
                <p>Fill in your database details in <code>app/config/mysql/default.db.php</code> and reload this page.
                    The database is created automatically if it does not exist.</p>
            </section>
        <?php endif ?>

        <h2>Documentation</h2>
        <ul>
            <li><a href="<?= $docs ?>#readme">README: installation, controllers, router, views and models</a></li>
            <li><a href="<?= $docs ?>/blob/main/help/Controllers/controllers.md">Controllers</a></li>
            <li><a href="<?= $docs ?>/blob/main/help/Request%20Handling%20Documentation/Request%20Handling%20Documentation.md">Request handling</a></li>
            <li><a href="<?= $docs ?>/tree/main/help">All help pages</a></li>
        </ul>

        <p>Create your first page by adding a folder in <code>site/</code> with a controller and a view.</p>
    </main>
</body>

</html>
