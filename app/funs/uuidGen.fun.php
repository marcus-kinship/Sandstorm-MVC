/**
 * Genererar en UUID (Universally Unique Identifier) enligt version 4.
 *
 * @return string En UUID i formatet XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX där varje X är en hexadecimal siffra.
 */
function uuidGen() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits för "time_low"
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),

        // 16 bits för "time_mid"
        mt_rand(0, 0xffff),

        // 16 bits för "time_hi_and_version",
        // de fyra mest signifikanta bitsen innehåller versionsnumret 4
        mt_rand(0, 0x0fff) | 0x4000,

        // 16 bits, 8 bits för "clk_seq_hi_res",
        // 8 bits för "clk_seq_low",
        // de två mest signifikanta bitsen innehåller noll och ett för variant DCE1.1
        mt_rand(0, 0x3fff) | 0x8000,

        // 48 bits för "node"
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
