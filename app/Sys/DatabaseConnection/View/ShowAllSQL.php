<?php require_once __DIR__ . '/include/header.php'; ?>
<body>
    <div class="preload"></div>
    <div class="row">
        <div class="window">
            <div class="title">Sandstorm - <?php echo _('Process Report'); ?> </div>
            <div class="content">
                <div class="dialog system-exception">
                    <div class="h1"><?php echo _('Executed SQL Queries'); ?></div>
                    <div class="messages">
                        <?php
                        $i = 1;
                        foreach ($this->allSQL as $index => $query): ?>
                            <div class="p" style="margin-top: 15px;">
                                <div style="display:flex; column-gap: 15px;">
                                    <div class="code" style="margin-top: 15px;">
                                        <div class="filname" style="justify-content: space-between">
                                            <span><?php echo sprintf(_('%d. SQL Query:'), $i); ?></span>
                                            <span><?php echo _('Type:'); ?>     <?php echo $query['type']; ?></span>
                                        </div>
                                        <div>
                                            <pre><code class="language-sql"><?php echo htmlspecialchars($query['sql']); ?></code></pre>
                                        </div>
                                    </div><?php if (!empty($query['args'])): ?>
                                        <div>
                                            <div class="code" style="margin-top: 15px;">
                                                <div class="filname"><?php echo _("SQL Query Arguments"); ?></div>
                                            </div>
                                            <div class="text">
                                                <table class="list">
                                                    <tr>
                                                        <td style="text-align: left; width:20px;"><?php echo _('No'); ?></td>
                                                        <td style="text-align: left; width:30px;"><?php echo _('Data Type'); ?>
                                                        </td>
                                                        <td style="text-align: left;"><?php echo _('Data'); ?></td>
                                                    </tr>
                                                    <?php foreach ($query['args'] as $argIndex => $arg): ?>
                                                        <tr>
                                                            <td><?php echo ($argIndex + 1); ?></td>
                                                            <td><?php echo htmlspecialchars($query['datatypes'][$argIndex]); ?></td>
                                                            <td><?php echo htmlspecialchars($arg); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php $i++; endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>