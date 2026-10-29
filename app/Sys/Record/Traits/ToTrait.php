<?php

namespace Sys\Record\Traits;

use \DatabaseConnection2;
use \Sys\Record\QueryBuilder\Inserts;
use \Sys\Record\QueryBuilder\Updates;
use \Sys\Record\SQL\Analyzer;
use \Sys\Record\SQL\Scheme;

/**
 * Trait för datainsättning/uppdatering
 *
 * @category _("Trait för datainsättning/uppdatering Trait")
 */

trait ToTrait
{


    /**
     * Funktion för att definiera en åtgärd som ska utföras efter att en SQL-fråga har körts.
     *
     * @param callable $function Callback-funktionen som ska utföras efter att SQL-frågan har körts.
     */
    function repairAfterSQLRun($function)
    {
        $this->repairAfterSQLRun = $function;
    }

    public function save($id = null)
    {
        // instans av MySQL-klass
        $this->db = new \DatabaseConnection();
        // Bygg upp frågan för att infoga data

        if ($id === null) {

            // Kontrollera om multi är sann flera instätt
            if ($this->multi) {

                // Om inget ID anges, infoga flera nya rader i tabellen
                $s = new Inserts($this->table);
                $sql[0] = $s->rows($this->properties, $this->_data);

                $this->db->getError = true;

                // Utför SQL-frågan och få det autogenererade ID:t för den nyligen infogade raden
                $id = $this->db->insertRow($sql);

                // Kontrollera om $id är false, vilket indikerar att frågan misslyckades
                if ($id === false) {

                    // Hämta antalet kolumner
                    $numColumns = count($this->properties);

                    // Loop för att försöka reparera felet ett begränsat antal gånger (antal kolumner)
                    for ($i = 0; $i < $numColumns; $i++) {
                        // Hämta felmeddelandet från databasanslutningen
                        $errorMessage = $this->db->getErrorMessage();
                        // Skapa en ny instans av SQL-analysören och skicka in felmeddelandet
                        $sqlAnalyzer = new Analyzer($errorMessage);
                        // Om reparationsförsöket lyckas, försök köra SQL-frågan igen
                        if ($sqlAnalyzer->typeSQL('insert')->table($this->table)->fields($this->properties)->arguments($this->_data)->repair()) {
                            $id = $this->db->insertRow($sql);
                            $this->hasRunSQL = true;
                            // Om SQL-frågan lyckas, returnera det autogenererade ID:et
                            if ($id !== false) {
                                $this->db->getError = false;
                                return $id;
                            }
                        }
                    }

                    $this->db->getError = false;
                    // Kasta ett undantag om reparationsförsök misslyckades
                    throw new \SystemException(_("Kunde inte reparera SQL-frågan."));
                }

                $this->db->getError = false;
                // Om allt gick bra, returnera det autogenererade ID för den nyligen infogade raden
                return $id;
            } else {
                // Om inget ID anges, infoga en ny rad i tabellen
                $s = new Inserts($this->table);
                $sql[0] = $s->row($this->properties);

                $this->db->getError = true;

                $sql = array_merge($sql, $this->data);
                
                $this->hasRunSQL = true;

                // Utför SQL-frågan och få det autogenererade ID:t för den nyligen infogade raden
                $id = $this->db->insertRow($sql);

                // Kontrollera om $id är false, vilket indikerar att frågan misslyckades
                if ($id === false) {

                    // Hämta antalet kolumner
                    $numColumns = count($this->properties);

                    // Loop för att försöka reparera felet ett begränsat antal gånger (antal kolumner)
                    for ($i = 0; $i < $numColumns; $i++) {
                        // Hämta felmeddelandet från databasanslutningen
                        $errorMessage = $this->db->getErrorMessage();
                        // Skapa en ny instans av SQL-analysören och skicka in felmeddelandet
                        $sqlAnalyzer = new Analyzer($errorMessage);
                        // Om reparationsförsöket lyckas, försök köra SQL-frågan igen
                        if ($sqlAnalyzer->typeSQL('insert')->table($this->table)->fields($this->properties)->arguments($this->data)->repair()) {
                            $id = $this->db->insertRow($sql);
                            // Om SQL-frågan lyckas, returnera det autogenererade ID:et
                            if ($id !== false) {
                                $this->db->getError = false;
                                return $id;
                            }
                        }
                    }

                    $this->db->getError = false;
                    // Kasta ett undantag om reparationsförsök misslyckades
                    throw new \SystemException(sprintf(_("Kunde inte reparera SQL-frågan. %s"), $errorMessage));
                }
                $this->db->getError = false;
                // Om allt gick bra, returnera det autogenererade ID för den nyligen infogade raden
                return $id;
            }
        } elseif (is_numeric($id)) {
            
            // Om ett numeriskt ID anges, uppdatera den befintliga raden med det angivna ID:et
            $s = new Updates($this->table);
            $this->define('id',$id);
            $sql = $s->force($this->properties, $this->data);
            $this->hasRunSQL = true;
            $this->db->update()->row($sql);
        } elseif (is_array($id)) {
            // Om en array med fält och värden anges, använd dem för att uppdatera eller infoga data
            $s = new Updates($this->table);
            if (empty($this->where)) {
                $this->where = $id;
            }
            
            // Kontrollera om multi är sann för flera uppdateringar
            if ($this->multi) {
                $sql = $s->where($this->where)->multiForce($this->_data, $this->properties);
            } else {
                $sql = $s->force($this->properties, $this->data);
            }
            
            $this->hasRunSQL = true;
            $this->db->update()->row($sql);
        }
    }

    public function createTableIfNotExists()
    {
        // Kontrollera om tabellen finns

        $sql = new Scheme($this->table);
        $sql->properties($this->properties);
        $sql->createTable();
        $stmt = $this->db->query($sql->get());

        if ($stmt->errno === 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Sätter värdet för en given egenskap.
     *
     * @param string $name Namnet på egenskapen.
     * @param mixed $value Värdet som ska tilldelas.
     * @param callable|null $callback (valfritt) Callback-funktionen som ska tillämpas på värdet.
     * @return Record Aktuell instans av Record-klassen.
     */
    public function set($name, $value, $callback = null)
    {
        // Kör callback-funktionen om den är angiven
        if ($callback !== null && is_callable($callback)) {
            $value = call_user_func($callback, $value);
        }

        $this->define($name, $value); // Sätt värdet för egenskapen
        return $this; // Returnera aktuell instans av klassen för metodkedja
    }

    /**
     * Funktionen `get` används för att hämta data från en instans baserat på nycklar och/eller ID:n.
     *
     * @param string $name Nyckeln som ska hämtas.
     * @param string|array|callable $ids (valfritt) ID:n eller ID-arrayen för att hämta data för flera poster i muliti läget eller en post eller Callback-funktionen som ska tillämpas på värdet innan det returneras .
     * @param callable|null $callback (valfritt) Callback-funktionen som ska tillämpas på värdet innan det returneras.
     * 
     * @return mixed Värdet för den angivna nyckeln eller nycklarna (med eller utan callback).
     * 
     * @throws SystemException Kastas om något av följande inträffar:
     *                         - Inget ID har angetts när det förväntades.
     *                         - Nyckeln eller ID:t som angivits finns inte i datan.
     */
    function get($name = "", $ids = "", $callback = null)
    {
        // Kontrollera om typen är sann (antagligen inställd någon annanstans)
        if ($this->_type) {
            // Kontrollera om $ids är tomt
            if (!empty($ids)) {
                // Kontrollera om $ids är en array
                if (is_array($ids)) {
                    $tmp = array(); // Skapa en temporär array för att lagra resultaten
                    foreach ($ids as $id) {
                        // Kontrollera om $id finns och om $name finns i dess data
                        if (key_exists($id, $this->_data) && key_exists($name, $this->_data[$id])) {
                            // Kontrollera om callback-funktionen är definierad och anropbar
                            if ($callback !== null && is_callable($callback)) {
                                // Tillämpa callback-funktionen på värdet och lagra det i $tmp
                                $tmp[$id][$name] = $this->_data[$id][$name] = call_user_func($callback, $this->_data[$id][$name]);
                            } else {
                                // Annars lagra värdet direkt i $tmp
                                $tmp[$id][$name] = $this->_data[$id][$name];
                            }
                        }
                    }
                    // Returnera resultatet för den sista $id/$name-kombinationen
                    return $tmp;
                } else {
                    // Kontrollera om $ids och $name finns i data
                    if (key_exists($ids, $this->_data) && key_exists($name, $this->_data[$ids])) {
                        // Kontrollera om callback-funktionen är definierad och anropbar
                        if (
                            $callback !== null && is_callable($callback)
                        ) {
                            // Tillämpa callback-funktionen på värdet och lagra det i data
                            $this->_data[$ids][$name] = call_user_func($callback, $this->_data[$ids][$name]);
                        }
                        // Returnera värdet för den angivna nyckeln
                        return $this->_data[$ids][$name];
                    } else {
                        // Kasta ett undantag om nyckeln eller ID inte finns
                        throw new \SystemException(sprintf(_("Du måste ange rätt id och nyckel, du har angett (%s, %s)"), $ids, $name));
                    }
                }
            } else {
                // Kasta ett undantag om inget ID har angetts
                throw new \SystemException(_("Ingen id har angetts"));
            }
        } else {
            // Om typen inte är sann
            if (key_exists($name, $this->data)) {
                // Kontrollera om $ids är definierad och en anropbar funktion
                if ($ids !== "" && is_callable($ids)) {
                    // Tillämpa callback-funktionen på värdet och lagra det i data
                    $this->data[$name] = call_user_func($ids, $this->data[$name]);
                }
                // Returnera värdet för den angivna nyckeln
                return $this->data[$name];
            } else {
                // Kasta ett undantag om nyckeln inte finns
                throw new \SystemException(sprintf(_("Du måste ange rätt nyckel, du har angett (%s)"), $name));
            }
        }
    }

    /**
     * Tar bort en rad från databastabellen.
     *
     * @param string $villkor Villkor för att välja vilken rad som ska tas bort, t.ex. "id = 5".
     * @return bool Returnerar true om raden togs bort framgångsrikt, annars false.
     */
    public function delete($villkor)
    {
        if ($villkor == "") {
            throw new \SystemException(_('Du måste ange Villkor för att välja vilken rad som ska tas bort, t.ex. id = 5.'));
        }
        // Skapar en instans av MySQL-klassen
        $db = new \DatabaseConnection();

        // Bygger upp SQL-frågan för att ta bort raden baserat på det angivna villkoret
        $sqlQuery = "DELETE FROM $this->table WHERE $villkor";

        // Utför SQL-frågan och få antalet berörda rader
        $resultat = $db->query($sqlQuery);

        // Returnerar true om minst en rad togs bort, annars false
        return $resultat > 0;
    }
}