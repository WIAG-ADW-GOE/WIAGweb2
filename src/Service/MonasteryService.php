<?php

namespace App\Service;


use App\Entity\Item;
use App\Entity\Institution;
use App\Entity\InstitutionPlace;
use App\Entity\Place;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Config\Definition\Exception\Exception;

use Doctrine\ORM\EntityManagerInterface;


class MonasteryService {

    const LIST_URL_GS = "https://api.gs.sub.uni-goettingen.de/v1/monasteries/list";
    const SINGLE_URL_GS = "https://api.gs.sub.uni-goettingen.de/v1/monastery";
    // Margin for TAQ, TPQ values
    const DATE_MARGIN = 20;

    private $client;
    private $entityManager;

    public function __construct(HttpClientInterface $client,
                                EntityManagerInterface $entityManager) {
        $this->client = $client;
        $this->entityManager = $entityManager;
    }

    /**
     * queryGSList($chunk_size, $offset)
     *
     * @return status code and list of gsn
     */
    public function queryGSList($chunk_size, $offset) {

        $response = $this->client->request('GET', self::LIST_URL_GS, [
            'query' => [
                'limit' => $chunk_size,
                'offset' => $offset
            ]
        ]);

        $statusCode = $response->getStatusCode();
        // $statusCode = 200
        if ($statusCode != 200) {
            throw new Exception($statusCode);
        }
        $contentType = $response->getHeaders()['content-type'][0];
        // $contentType = 'application/json'
        // $content = $response->getContent();
        // $content = '{"id":521583, "name":"symfony-docs", ...}'
        $content = $response->toArray();
        // $content = ['id' => 521583, 'name' => 'symfony-docs', ...]

        $set_size = $content["total"];

        // extract gsn
        $gsn_list = [];
        foreach($content["list"] as $monastery) {
            $gsn_list[] = $monastery["gsnId"];
        }

        return [
            "set_size" => $set_size,
            "list" => $gsn_list,
        ];

    }

    /**
     * update($gsn)
     *
     * update institution where institution.idGsn is $gsn
     */
    public function update($monastery) {

        $gsn = $monastery->getIdGsn();
        $data = $this->queryGSByGsn($gsn);

        // via the API we cannot access gs_monastery.id_monastery
        $monastery->setName($data["name"]);

        if (array_key_exists("note", $data)) {
            $field_size = 2047;
            $monastery->setNote(substr($data["note"], 0, $field_size));
        }

        // update $monastery->getInstitutionPlace()
        // - remove entries
        $ip_list = $monastery->getInstitutionPlace();
        foreach ($ip_list as $ip) {
            $ip_list->removeElement($ip);
            $ip->setInstitution(null);
            $this->entityManager->remove($ip);
        }

        $placeRepository = $this->entityManager->getRepository(Place::class);
        foreach ($data["locations"] as $data_location) {
            $ip_new = new InstitutionPlace();
            $ip_new->setInstitution($monastery);
            $ip_list->add($ip_new);
            $place = $placeRepository->findByGeonamesId($data_location["geonamesId"]);

            // set place
            // - examine missing places in an extra process
            if (!is_null($place) && count($place) > 0) {
                $ip_new->setPlaceId($place[0]->getId());
                $ip_new->setPlaceName($place[0]->getName());
            } else {
                $ip_new->setPlaceName($data_location["placeName"]);
            }

            // set dates
            $this->setDates($ip_new, $data_location);

            $this->entityManager->persist($ip_new);
        }

        return $monastery;
    }

    private function setDates($institution_place, $data) {
        // set dates
        // 2022-11-14 TAQ-values are not accessible via the API
        $institution_place->setNoteBegin($data["from"]);
        $institution_place->setNoteEnd($data["to"]);
        $date_key_fnc_list = [
            "beginTPQ" => "setDateBeginTpq",
            "endTPQ" => "setDateEndTpq",
            "beginTAQ" => "setDateBeginTaq",
            "endTAQ" => "setDateEndTaq"
        ];
        $this->setByKeyList($institution_place, $data, $date_key_fnc_list);

        // set num_date_begin, num_date_end
        $num_date_begin = $institution_place->getDateBeginTpq();
        if (!is_null($num_date_begin)) {
            $institution_place->setNumDateBegin($num_date_begin);
        } else {
            $num_date_begin = $institution_place->getDateBeginTaq();
            if (!is_null($num_date_begin)) {
                $institution_place->setNumDateBegin($num_date_begin - self::DATE_MARGIN);
            }
        }

        $num_date_end = $institution_place->getDateEndTaq();
        if (!is_null($num_date_end)) {
            $institution_place->setNumDateEnd($num_date_end);
        } else {
            $num_date_end = $institution_place->getDateEndTpq();
            if (!is_null($num_date_end)) {
                $institution_place->setNumDateEnd($num_date_end + self::DATE_MARGIN);
            }
        }
        return $institution_place;
    }

    /**
     *
     */
    private function setByKeyList($obj, $data, $key_fnc_list) {
        foreach($key_fnc_list as $key => $fnc) {
            if (array_key_exists($key, $data)) {
                $obj->$fnc($data[$key]);
            }
        }
    }

    /**
     * queryGSByGsn($gsn)
     *
     * @return status code and list of gsn
     */
    private function queryGSByGsn($gsn) {

        $response = $this->client->request('GET', self::SINGLE_URL_GS.'/'.$gsn);

        $statusCode = $response->getStatusCode();
        if ($statusCode != 200) {
            throw new Exception($statusCode);
        }

        $contentType = $response->getHeaders()['content-type'][0];
        // $content = $response->getContent(); // json
        $content = $response->toArray();

        return $content;
    }

};
