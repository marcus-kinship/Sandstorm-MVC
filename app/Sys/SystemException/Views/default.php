    <?php  SystemException::header();?>
    <div class="row">
        <div class="window">
            <div class="title">Sandstorm - <?php  print _('Aktivitetshanteraren'); ?></div>
            <div class="content">
                <div class="dialog system-exception">
                    <div class="h1"><?php  print sprintf(_('%s Säger'), $title) ?></div>
                    <?php  if ($shortCode) { ?>
                        <span class="erorr-type"><?php  print $shortCode ?>: </span>
                    <?php  } ?>

                    <?php  if ($messages) {
                        foreach ($messages as $m) {
                            print $m;
                        }
                    }
                    ?>

                    <div class="p">
                        <div class="wrap two">
                            <div class="code">
                                <div class="filname">
                                    <?php 
                                    if (!empty($where)) {
                                        $lkey =  array_key_last($where);
                                        $p = sprintf(_("%s i %s på %d"), $where[$lkey], $basePath, $line);
                                    } else {
                                        $p = "";
                                    }
                                    ?>
                                    <?php  print $p;  ?>
                                </div>
                                <table>
                                    <tbody>
                                        <?php  foreach ($retrieveText as $value) { ?>
                                            <tr class="row">
                                                <td class="line">
                                                    <?php  print $row; ?>
                                                </td>
                                                <td <?php 
                                                    if ($row == $line) {
                                                        print ' class="error text"';
                                                    } else {
                                                        print ' class="text"';
                                                    }
                                                    ?>>
                                                    <div style=" white-space: pre"><?php  print str_replace("\n", "&#9;", htmlentities($value)); ?></div>
                                                </td>
                                            </tr>
                                        <?php  $row++;
                                        } ?>
                                    </tbody>
                                </table>
                            </div>
                            <div style="margin-top: 35px;">
                                <table class="list">
                                    <thead>
                                        <tr class="head">
                                            <td style="border-top-left-radius: 4px;"><?php  print _('Stack'); ?></td>
                                            <td style="border-top-right-radius: 4px;"><?php  print _('Rad'); ?></td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($debug_backtrace_array as $trace) { ?>
                                            <tr>
                                                <td><?php  print htmlspecialchars($trace['stack']); ?></td>
                                                <td><?php  print $trace['row']; ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dialog classes">
                    <div class="h1"><?php  print _('Klasser som har laddats'); ?></div>
                    <div class="p">
                        <table class="list">
                            <thead>
                                <tr class="head">
                                    <td style="border-top-left-radius: 4px;"><?php  print _('Klasser'); ?></td>
                                    <td><?php  print _('Typ'); ?></td>
                                    <td style="border-top-right-radius: 4px;"><?php  print _('Tidpunkt'); ?></td>
                                </tr>
                            </thead>
                            <tbody>
                                <?php  foreach ($getRegistry as $class => $type) { ?>
                                    <tr>
                                        <td><?php  print ucfirst($class); ?></td>
                                        <td><?php  print $type['type']; ?></td>
                                        <td><?php  print round($type['time'] - $_SERVER["REQUEST_TIME_FLOAT"], 4); ?></td>
                                    </tr>
                                <?php  } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="dialog post-data">
                    <div class="h1"><?php  print _('Post data'); ?></div>
                    <div class="p">
                        <table class="list">
                            <thead>
                                <tr class="head">
                                    <td style="border-top-left-radius: 4px;"><?php  print _('Namn'); ?></td>
                                    <td style="border-top-right-radius: 4px;"><?php  print _('Data'); ?></td>
                                </tr>
                            </thead>
                            <tbody>
                                <?php  foreach ($getPostData as $name => $type) { ?>
                                    <tr>
                                        <td><?php  print $name; ?></td>
                                        <td><?php  print $type; ?></td>
                                    </tr>
                                <?php  } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="button-container">
                    <div class="aero-button system-exception">
                        <?php  print $title; ?>
                        <div class="after"></div>
                    </div>
                    <div class="aero-button classes">
                        <?php  print _('Klasser'); ?>
                        <div class="after"></div>
                    </div>
                    <div class="aero-button post-data">
                        <?php  print _('Post Data'); ?>
                        <div class="after"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>