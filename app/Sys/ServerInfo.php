<?php

/**
 * ServerInfo klass
 * 
 * Denna klass tillhandahåller metoder för att hämta olika typer av serverinformation.
 * Den kan användas för att samla information om servern, klienten och deras interaktion.
 * Klassen är användbar för debugging, loggning och serveradministration.
 * 
 * @category _("ServerInfo klass")
 */
class ServerInfo {
    /**
     * Hämtar serverns IP-adress
     *
     * @return string Serverns IP-adress
     */
    public function getServerIp() {
        return $_SERVER['SERVER_ADDR'];
    }

    /**
     * Hämtar användarens IP-adress, tar hänsyn till om de använder en proxy
     *
     * @return string Klientens IP-adress
     */
    public function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    /**
     * Hämtar landsinformation baserat på en given IP-adress
     *
     * @param string $ip IP-adressen att söka efter
     * @return object JSON-decodad respons innehållande landsinformation
     */
    public function getCountryByIp($ip)
    {
        $url = 'http://www.ipnr.nu/ajax/';
        $myvars = 'ip=' . $ip;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $myvars);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);

        curl_close($ch);

        return json_decode($response);
    }

    /**
     * Hämtar serverns namn (domän)
     *
     * @return string Serverns namn
     */
    public function getServerName() {
        return $_SERVER['SERVER_NAME'];
    }

    /**
     * Hämtar serverns portnummer
     *
     * @return int Serverns portnummer
     */
    public function getServerPort() {
        return $_SERVER['SERVER_PORT'];
    }

    /**
     * Hämtar serverns protokoll (http eller https)
     *
     * @return string Serverns protokoll ('http' eller 'https')
     */
    public function getServerProtocol() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    }

    /**
     * Hämtar användarens webbläsarinformation
     *
     * @return string Användarens webbläsarinformation (User Agent-sträng)
     */
    public function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'];
    }

    /**
     * Hämtar den aktuella servern tiden
     *
     * @return string Aktuell servertid i formatet 'Y-m-d H:i:s'
     */
    public function getServerTime() {
        return date('Y-m-d H:i:s');
    }

    /**
     * Hämtar all information som en array
     *
     * @return array Associativ array med all serverinformation
     */
    public function getAllInfo() {
        $clientIp = $this->getClientIp();
        return [
            'server_ip' => $this->getServerIp(),
            'client_ip' => $clientIp,
            'server_name' => $this->getServerName(),
            'server_port' => $this->getServerPort(),
            'server_protocol' => $this->getServerProtocol(),
            'user_agent' => $this->getUserAgent(),
            'server_time' => $this->getServerTime(),
            'client_country' => $this->getCountryByIp($clientIp)
        ];
    }
}

