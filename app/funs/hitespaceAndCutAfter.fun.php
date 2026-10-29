/**
 * Hanterar vita mellanslag och kapar en sträng om den överskrider en viss längd, med möjlighet att lägga till ett suffix.
 * Lägger även till en radbrytning (<br>) om det inte finns några vita mellanslag i strängen.
 *
 * @param string $str Den ursprungliga strängen.
 * @param int $len Önskad längd innanför vilken strängen inte ska kapas.
 * @param string $append (Valfri) Suffix som läggs till om strängen kapas.
 *
 * @return string Den modifierade strängen med möjlig kapning och radbrytning.
 */
function WhitespaceAndCutAfter($str, $len = 25, $append = '...') {
    // Kontrollera om det finns några vita mellanslag i strängen
    if (!preg_match('/\s/', $str)) {
        // Kontrollera om strängen är längre än den önskade längden
        return (strlen($str) > $len) ?
            substr($str, 0, $len - strlen($append)) . $append . '<br>' :
            $str;
    }
    // Om det finns vita mellanslag, returnera orörd sträng utan kapning eller radbrytning
}
