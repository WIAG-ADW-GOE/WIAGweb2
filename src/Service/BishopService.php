<?php

namespace App\Service;


use App\Entity\Person;
use App\Repository\PersonRepository;

use Symfony\Component\HttpFoundation\Response;

use Doctrine\ORM\EntityManagerInterface;


class BishopService {

    private $repository;

    public function __construct(PersonRepository $repository) {
        $this->repository = $repository;
    }


};
