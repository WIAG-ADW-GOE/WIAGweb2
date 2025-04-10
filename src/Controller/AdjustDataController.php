<?php
namespace App\Controller;

use App\Entity\Item;
use App\Entity\ItemReference;
use App\Entity\ItemProperty;
use App\Entity\ItemPropertyType;
use App\Entity\UrlExternal;
use App\Entity\Person;
use App\Entity\InputError;
use App\Entity\PersonRole;
use App\Entity\PersonRoleProperty;
use App\Entity\RolePropertyType;
use App\Entity\Authority;
use App\Entity\NameLookup;
use App\Entity\CanonLookup;
use App\Entity\UserWiag;
use App\Entity\Role;

use App\Service\EditPersonService;
use App\Service\UtilService;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Doctrine\ORM\EntityManagerInterface;

class AdjustDataController extends AbstractController {

    private $editService;
    private $entityManager;

    public function __construct(EditPersonService $editService,
                                EntityManagerInterface $entityManager) {
        $this->entityManager = $entityManager;
        $this->editService = $editService;
    }

    /**
     * update dateRange of persons
     */
    #[Route(path: '/edit/person/data-adjust/date-range', name: 'edit_data_date_range')]
    public function dateRange(Request $request) {

        $this->denyAccessUnlessGranted('ROLE_DATA_EDIT');

        $personRepository = $this->entityManager->getRepository(Person::class);

        $limit = null;
        $offset = 0;
        $id_list = $personRepository->findMissingDateRange($limit, $offset);

        $person_list = $personRepository->findList($id_list);

        foreach ($person_list as $person) {
            EditPersonService::setNumDates($person);
            EditPersonService::updateDateRange($person);
        }

        $this->entityManager->flush();


        $template = 'edit_person/adjust_data.html.twig';
        return $this->render($template, [
            'personList' => $person_list,
        ]);

    }

}
