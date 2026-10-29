/**
 * Kodar om en array rekursivt till UTF-8, inklusive strängar och objekt.
 *
 * @param mixed $dat Den ursprungliga datan, antingen en sträng, en array eller ett objekt.
 *
 * @return mixed Den omkodade datan i UTF-8.
 */
function array_utf8_encode_recursive($dat) {
    // Kontrollera om det är en sträng
    if (is_string($dat)) {
        return utf8_encode($dat);
    }

    // Kontrollera om det är ett objekt
    if (is_object($dat)) {
        // Hämta objektets egenskaper
        $ovs = get_object_vars($dat);
        $new = $dat;

        // Loopa igenom egenskaperna och kod om rekursivt
        foreach ($ovs as $k => $v) {
            $new->$k = array_utf8_encode_recursive($new->$k);
        }

        return $new;
    }

    // Kontrollera om det är en array
    if (!is_array($dat)) {
        // Returnera datan som den är om det inte är någon av ovanstående
        return $dat;
    }

    // Om det är en array, loopa igenom och kod om rekursivt
    $ret = array();
    foreach ($dat as $i => $d) {
        $ret[$i] = array_utf8_encode_recursive($d);
    }

    return $ret;
}
