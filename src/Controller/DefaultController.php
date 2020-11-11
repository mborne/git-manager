<?php

namespace MBO\GitManager\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use MBO\GitManager\Filesystem\LocalFilesystem;

class DefaultController extends Controller
{
    /**
     * @var LocalFilesystem
     */
    private $localFilesystem;

    public function __construct(LocalFilesystem $localFilesystem){
        $this->localFilesystem = $localFilesystem ;
    }


    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        $repositories = json_decode($this->localFilesystem->read('repositories.json'), true) ;
        return $this->render('default/index.html.twig', [
            'repositories' => $repositories
        ]);
    }
}
