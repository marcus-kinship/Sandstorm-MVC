/**
 * Kortar ner en sträng om den överskrider en viss längd och lägger till ett suffix.
 *
 * @param string $string Den ursprungliga strängen.
 * @param int $len Önskad längd innanför vilken strängen inte ska kapas.
 * @param string $append (Valfri) Suffix som läggs till om strängen kapas.
 *
 * @return string Den modifierade strängen.
 */
function cutAfter($string, $len = 30, $append = '...') {
    // Kontrollera om strängen är längre än den önskade längden
    if (strlen($string) > $len) {
        // Kapa strängen och lägg till suffix
        return substr($string, 0, $len - strlen($append)) . $append;
    } else {
        // Returnera orörd sträng om den inte överskrider längden
        return $string;
    }
}
