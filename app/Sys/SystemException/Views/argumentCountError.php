    <?php  SystemException::header();?>
    <div class="row">
        <div class="window">
            <div class="title">Sandstorm - <?php  print _('Aktivitetshanteraren'); ?></div>
            <div class="content">
                <div class="dialog system-exception">
                    <div class="h1"><?php  print sprintf(_('%s Säger'), $title); ?></div>
                    <div class="messages">
                        <span class="erorr-type"><?php  print $shortCode; print " : ".sprintf(_("On line %d in the file %s, the function %s is expected to receive the required argument(s)"),$callLine,$callFile,str_replace($_SERVER["DOCUMENT_ROOT"],'',$basePath)); ?></span>
                         
                        <?php 
                        if (!empty($functionInfo)) {
                        ?>
                        <div class="p" style="display:block;">
                        <div class="wrap two">
                                 <div class="code">
                                           <?php 
                          if (!empty($functionInfo['phpdoc'])) {
                                echo '<div class="h1" style="    white-space: break-spaces;">' .$functionInfo['phpdoc']."</div>";
                            }?>             
                                     
                        <div class="h1">
                            <?php 
                            // Skriver ut funktionsnamnet
                            echo _("Funktion: ").htmlspecialchars($functionInfo['function']);
                            ?>
                        </div>
                    
                        <div>
                            <?php
                            // Skriver ut funktionsargumenten om de finns
                            if (!empty($functionInfo['arguments'])) {
                                echo _("Förväntade Argument: ").' <span class="error text" style="padding: 4px 9px;">' . implode(', ', array_map('htmlspecialchars', $functionInfo['arguments']))."</span>";
                            }?>
                            </div></div></div>
                        <?php 
                        }
?>
  
                    </div></div>
                    <div class="p">
                        <div class="wrap two">
                             <div class="code"  style="margin-top: 35px;">
                          <?php     
                            $p = sprintf(_(" %s på %d"), $basePath, $line);
                        
                       
                        ?>
                                <div class="filname">

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
                            <div class="code"  style="margin-top: 35px;">
                          <?php                             if(!empty($where)){
                             $lkey =  array_key_last($where);
                            $p = sprintf(_("%s i %s på %d"), $where[$lkey], $callFile, $callLine);
                        }else{
                            $p = "";
                        }
                       
                        ?>
                                <div class="filname">

                                    <?php  print $p;  ?>
                                </div>
                                <table>
                                    <tbody>
                                        <?php  foreach ($retrieveTextCall as $value) { ?>
                                            <tr class="row">
                                                <td class="line">
                                                    <?php  print $rowCall; ?>
                                                </td>
                                                <td <?php 
                                                    if ($rowCall == $callLine) {
                                                        print ' class="error text"';
                                                    } else {
                                                        print ' class="text"';
                                                    }
                                                    ?>>
                                                    <div style=" white-space: pre"><?php  print str_replace("\n", "&#9;", htmlentities($value)); ?></div>
                                                </td>
                                            </tr>
                                        <?php  $rowCall++;
                                        } ?>
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
