<!DOCTYPE html>
<html lang="sv">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Välkommen till Sandstorm-MVC</title>
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

        code {
            background: #e9dfcc;
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <main>
        <h1>Välkommen till Sandstorm-MVC</h1>
        <p>Sandstorm är igång. Den här sidan är vyn <code>site/default/start.php</code>, som laddas av
            <code>DefaultController::index()</code> i <code>site/default/Default.class.php</code>.</p>
        <p>Skapa din första sida genom att lägga till en mapp i <code>site/</code> med en controller och en vy.
            Läs mer i <code>README.md</code> och i mappen <code>help/</code>.</p>
    </main>
</body>

</html>
