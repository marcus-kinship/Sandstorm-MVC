<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Something went wrong</title>
    <?php include __DIR__ . '/style.php'; ?>
</head>

<body>
    <div class="window">
        <div class="title">Sandstorm - 500</div>
        <h1>Something went wrong</h1>
        <p>Please try again in a moment.</p>
        <?php if (!empty($errorId)): ?>
            <p class="id">Error id: <?= htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif ?>
        <p><a class="aero-button" href="/">Go to the start page</a></p>
    </div>
</body>

</html>
