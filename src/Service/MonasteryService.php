<?php

namespace App\Service;


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

        // extract gsn
        $gsn_list = [];
        foreach($content["list"] as $monastery) {
            $gsn_list[] = $monastery["gsnId"];
        }

        return $gsn_list;

    }

    /**
     * update($gsn)
     *
     * update institution where institution.idGsn is $gsn
     */
    public function update($gsn) {
        $repository = $this->entityManager->getRepository(Institution::class);

        $monastery_list = $repository->findByIdGsn($gsn);
        $updated_flag = false;
        if (is_null($monastery_list) || $monastery_list == []) {
            return $updated_flag;
        }
        $monastery = $monastery_list[0];

        $data = $this->queryGSByGsn($gsn);

        // via the API we cannot access gs_monastery.id_monastery
        $monastery->setIdGsn($data["gsnId"]);
        $monastery->setName($data["name"]);
        $monastery->setNote($data["note"]);


        // update $monastery->getInstitutionPlace()
        // - remove entries
        $inst_place_list = $monastery->getInstitutionPlace();
        foreach ($inst_place_list as $inst_place) {
            $inst_place_list->removeElement($inst_place);
            $inst_place->setInstitution(null);
            $this->entityManager->remove($inst_place);
        }

        $placeRepository = $this->entityManager->getRepository(Place::class);
        foreach ($data["locations"] as $data_location) {
            $inst_place_new = new InstitutionPlace();
            $inst_place_new->setInstitution($monastery);
            $inst_place_list->add($inst_place_new);
            $place = $placeRepository->findByGeonamesId($data_location["geonamesId"]);
            // look for missing places in an extra process

            if (!is_null($place) && count($place) > 0) {
                $inst_place_new->setPlace($place[0]);
            }

            // set dates
            $inst_place_new->setNoteBegin($data_location["from"]);
            $inst_place_new->setNoteEnd($data_location["to"]);
            $date_key_list = ["beginTPQ", "endTPQ", "beginTAQ", "endTAQ"];
            $this->setDates($inst_place_new, $data_location, $date_key_list);



            // TODO set num_date_begin, num_date_end

            $entityManager->persist($inst_place_new);
        }

        $updated_flag = true;

        // dump($monastery[0], count($monastery[0]->getInstitutionPlace()));

        return $updated_flag;
    }

    /**
     *
     */
    private function setDates($obj, $data, $key_list) {
        foreach($key_list as $key) {
            if (array_key_exists($key, $data)) {
                $fnc = "setDate".ucfirst($key);
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

    /**
     *
     */
    public function create($gsn) {
        return false;
    }

};
