<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Trick;
use App\Form\TrickType;
use App\Repository\TrickRepository;
use App\Service\ImageManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TrickController extends AbstractController
{
    /**
     * @Route("/trick/{id}", name="trick")
     *
     *
     * @return Response
     */
    public function show(Trick $trick): Response
    {
        $user = $this->getUser();

        return $this->render('trick/index.html.twig',
            [
                'user' => $user,
                'trick' => $trick
            ]);
    }

    /**
     * @Route("/add-comment", name="add_comment")
     *
     * @param TrickRepository $trickRepository
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     *
     * @return Response
     */
    public function addComment(TrickRepository $trickRepository, EntityManagerInterface $entityManager, Request $request): Response
    {
        $user = $this->getUser();

        $request = $request->request->all();
        $trickId = $request['trickId'];
        $content = $request['comment'];

        $trick = $trickRepository->findOneBy(['id' => $trickId]);

        $comment = new Comment();
        $trick->addComment($comment);
        $comment->setContent($content);
        $comment->setUser($user);
        $entityManager->persist($comment);
        $entityManager->flush();

        return $this->redirect($this->generateUrl('trick', ['id' => $trick->getId()]));
    }

    /**
     * @Route("/trick/create", name="trick_create")
     */
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        $trick = new Trick();
        $form = $this->createForm(TrickType::class, $trick);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $trick = $form->getData();
            $trick->setUser($user);
            $entityManager->persist($trick);
            $entityManager->flush();

            return $this->redirectToRoute('home');
        }

        return $this->render('trick/create.html.twig', ['form' => $form->createView()]);
    }

    /**
     * @Route("/trick/edit", name="trick_edit")
     *
     */
    public function edit(Request $request, TrickRepository $trickRepository, EntityManagerInterface $entityManager)
    {
        $trickId = $request->query->get('id');
        $trick = $trickRepository->findOneBy(['id' => $trickId]);

        $form = $this->createForm(TrickType::class, $trick);
        $form->remove('images');
        $form->remove('mainImage');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $name = $form->get('name')->getData();
            $description = $form->get('description')->getData();

            $trick->setName($name);
            $trick->setDescription($description);

            $entityManager->persist($trick);
            $entityManager->flush();

            return $this->redirectToRoute('home');
        }

        return $this->render('trick/edit.html.twig', ['form' => $form->createView(), 'trick' => $trick]);
    }

    /**
     * @Route("/trick/edit-details", name="trick_details_edit")
     *
     */
    public function editInDetails(Request $request, TrickRepository $trickRepository, EntityManagerInterface $entityManager,
                                  ImageManager $imageManager)
    {
        $trickId = $request->query->get('id');
        $trick = $trickRepository->findOneBy(['id' => $trickId]);

        $form = $this->createForm(TrickType::class, $trick);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $name = $form->get('name')->getData();
            $description = $form->get('description')->getData();

            $trick->setName($name);
            $trick->setDescription($description);

            foreach ($trick->getImages() as $image) {
                $image->setTrick($trick);
                $image->setCaption($image->getCaption());
                $image = $imageManager->saveImage($image);

                $entityManager->persist($image);

                $imageManager->crop($image);
                $imageManager->resize($image);
            }

            foreach ($trick->getVideos() as $video) {

                $video->setTrick($trick);
                $video->setUrl($video->getUrl());
                $entityManager->persist($video);
            }

            $entityManager->persist($trick);
            $entityManager->flush();

            return $this->redirectToRoute('home');
        }

        return $this->render('trick/edit_details.html.twig', ['form' => $form->createView(), 'trick' => $trick]);
    }
}

