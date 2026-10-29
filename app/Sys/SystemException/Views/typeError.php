    <? SystemException::header();?>
    <div class="row">
        <div class="window">
            <div class="title">Sandstorm - <? print _('Aktivitetshanteraren'); ?></div>
            <div class="content">
                <div class="dialog system-exception">
                    <div class="h1"><? print sprintf(_('%s Säger'), $title) ?></div>
                    <div class="messages">
                        <span class="erorr-type"><? print $shortCode ?>: 
                       <?
                        if ($message) {
                           print $message;
                        } ?>
</span> <?
                        if (!empty($where)) {
                            $lkey =  array_key_last($where);
                            $p = sprintf(_("%s i %s på %d"), $where[$lkey], $basePath, $line);
                        } else {
                            $p = "";
                        }

                        ?>
                    </div>
                    <div class="p">
                        <div class="wrap two">
                            <div class="code">
                                <div class="filname">

                                    <? print $p;  ?>
                                </div>
                                <table>
                                    <tbody>
                                        <? foreach ($retrieveText as $value) { ?>
                                            <tr class="row">
                                                <td class="line">
                                                    <? print $row; ?>
                                                </td>
                                                <td <?
                                                    if ($row == $line) {
                                                        print ' class="error text"';
                                                    } else {
                                                        print ' class="text"';
                                                    }
                                                    ?>>
                                                    <div style=" white-space: pre"><? print str_replace("\n", "&#9;", htmlentities($value)); ?></div>
                                                </td>
                                            </tr>
                                        <? $row++;
                                        } ?>
                                    </tbody>
                                </table>
                            </div>
                            <div style="margin-top: 35px;">
                                <table class="list">
                                    <thead>
                                        <tr class="head">
                                            <td style="border-top-left-radius: 4px;"><? print _('Stack'); ?></td>
                                            <td style="border-top-right-radius: 4px;"><? print _('Rad'); ?></td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($debug_backtrace_array as $trace) { ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    echo htmlspecialchars(
                                                        ($trace['file'] ?? 'Fil saknas') . ' ' . ($trace['function'] ?? 'Funktion saknas')
                                                    ); 
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php echo $trace['line'] ?? 'Radnummer saknas'; ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dialog classes">
                    <div class="h1"><? print _('Klasser som har laddats'); ?></div>
                    <div class="p">
                        <table class="list">
                            <thead>
                                <tr class="head">
                                    <td style="border-top-left-radius: 4px;"><? print _('Klasser'); ?></td>
                                    <td><? print _('Typ'); ?></td>
                                    <td style="border-top-right-radius: 4px;"><? print _('Tidpunkt'); ?></td>
                                </tr>
                            </thead>
                            <tbody>
                                <? foreach ($getRegistry as $class => $type) { ?>
                                    <tr>
                                        <td><? print ucfirst($class); ?></td>
                                        <td><? print $type['type']; ?></td>
                                        <td><? print round($type['time'] - $_SERVER["REQUEST_TIME_FLOAT"], 4); ?></td>
                                    </tr>
                                <? } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="dialog post-data">
                    <div class="h1"><? print _('Post data'); ?></div>
                    <div class="p">
                        <table class="list">
                            <thead>
                                <tr class="head">
                                    <td style="border-top-left-radius: 4px;"><? print _('Namn'); ?></td>
                                    <td style="border-top-right-radius: 4px;"><? print _('Data'); ?></td>
                                </tr>
                            </thead>
                            <tbody>
                                <? foreach ($getPostData as $name => $type) { ?>
                                    <tr>
                                        <td><? print $name; ?></td>
                                        <td><? print $type; ?></td>
                                    </tr>
                                <? } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="button-container">
                    <div class="aero-button system-exception">
                        <? print $title; ?>
                        <div class="after"></div>
                    </div>
                    <div class="aero-button classes">
                        <? print _('Klasser'); ?>
                        <div class="after"></div>
                    </div>
                    <div class="aero-button post-data">
                        <? print _('Post Data'); ?>
                        <div class="after"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>