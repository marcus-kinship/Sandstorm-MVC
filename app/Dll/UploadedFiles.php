<?php 

/**
 * Class UploadedFiles
 * A class for managing uploaded files
 * 
 * @category _("File Management Class")
 */
class UploadedFiles
{

    public $error;
    public $submit;
    public $field;
    public $path;
    public $maxsize;
    public $maxfiles;
    public $extension;
    public $success;
    private $files = array();
    public $message;

    /**
     * Constructor
     * 
     * @param string $submit The name of the submit button
     */
    function __construct($submit)
    {
        if (empty($submit)) {
            $this->error = _("Välj en submit knapp");
        } else {
            $this->submit = $submit;
        }
    }

    /**
     * Convert error code to error message
     * 
     * @param int $code The error code
     * @return string The corresponding error message
     */
    private function codeToMessage($code)
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                $message = _("Du får inte överstiga 2 mb per bild");
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = _("Den här filen har överstigit MAX_FILE_SIZE värdet som anges i HTML-formuläret");
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = _("Den här filen har endast delvis laddats upp");
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = _("Ingen fil laddades upp");
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = _("En temporär katalog saknas");
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = _("Det gick inte att skriva till hårddisken");
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = _("Den här filen har stoppats på grund av ett anslutningsfel");
                break;
            default:
                $message = _("Okänt uppladdningsfel");
                break;
        }
        return $message;
    }

    /**
     * Validate and process uploaded files
     * 
     * @return void
     */
    function valid()
    {

        if (Request::post($this->submit)) {
            $files = Request::files($this->field);


            if (!is_array($files["name"])) {

                $tmp = array();
                foreach ($files as $key => $name) {
                    $tmp[$key][0] = $name;
                }
                $files = $tmp;
            }


            $dir = $_SERVER['DOCUMENT_ROOT'] . $this->path . '/';

            if (!is_dir($dir)) {

                $pieces = explode("/", trim($this->path, '/'));

                $tmpfolder = $_SERVER['DOCUMENT_ROOT'];
                foreach ($pieces as $name) {

                    $tmpfolder .= '/' . $name;
                    if (!is_dir($tmpfolder)) {
                        mkdir($tmpfolder, 0700);
                    }
                }
            }


            foreach ($files as $key => $name) {
                foreach ($name as $i => $value) {
                    $path_parts = pathinfo($files["name"][$i]);
                    if ($files["error"][$i] == UPLOAD_ERR_OK) {
                        if (count($files) > $this->maxfiles || $this->maxfiles == "") {
                            if (($files["size"][$i] < $this->maxsize)) {
                                if (in_array(strtolower($path_parts['extension']), $this->extension)) {

                                    $newname = uuidGen() . '.' . $path_parts['extension'];

                                    if (move_uploaded_file($files['tmp_name'][$i], $dir . $newname)) {

                                        $this->success = _($this->message);

                                        $this->files[] = array(
                                            'tmp_name' => $files["name"][$i],
                                            'name' => $this->path . '/' . $newname,
                                            'size' => $files['size'][$i]
                                        );
                                    } else {
                                    }
                                } else {
                                    $extension = implode(",", $this->extension);
                                    $msg = _("Filen måste ha följande filtyper: ");
                                    $this->error = $msg . $extension;
                                }
                            } else {
                                $msg = _("Du får inte överstiga 2 mb per bild");
                                $this->error = $msg;
                            }
                        } else {
                            $msg = sprintf(_("Du kan bara ladda upp högst %d filer"), $this->maxfiles);
                            $this->error = $msg;
                        }
                    } else {
                        $msg = $this->codeToMessage($files["error"][$i]);
                        $this->error = $msg;
                    }
                }
            }
        }
    }

    /**
     * Get uploaded files
     * 
     * @return array The uploaded files
     */
    function getFiles()
    {
        $this->valid();

        return $this->files;
    }
}
