<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\Slugger\SluggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Repository\BlogRepository;
use App\Entity\Blog;
use App\Form\BlogFormType;
use App\Form\EditBlogType;
use Psr\Log\LoggerInterface;

class MainController extends AbstractController
{
    /**
     * @Route("/", name="")
     *
     * @param BlogRepository $blogRepository
     *
     * @return Response
     */
    public function index(BlogRepository $blogRepository)
    {
        $blogs = $blogRepository->findAll();
        return $this->render('list.html.twig', ['blogs'=>$blogs]);
    }

     /**
     * @Route("/create", name="create")
     *
     * @param Request $request
     *
     * @return Response
     */
    public function createBlog(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger)
    {
        $blog = new Blog();
        $form = $this->createForm(BlogFormType::class, $blog);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $blog = $form->getData();
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('image_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Image cannot be saved.');
                }
                $blog->setImage($newFilename);
            } else {
                $blog->setImage('default.png');
            }

            $entityManager->persist($blog);
            $entityManager->flush();
            $this->addFlash('success', 'Blog was created!');
            return $this->redirectToRoute('');
        }	

        return $this->render('create.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/edit/{id}", name="edit")
     *
     * @ParamConverter("blog", class="App:Blog")
     *
     * @return Response
     */
    public function editBlog(Blog $blog, Request $request,
        EntityManagerInterface $entityManager, SluggerInterface $slugger,
        BlogRepository $blogRepository, LoggerInterface $logger)
    {
        $form = $this->createForm(EditBlogType::class, $blog);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $blog      = $form->getData();
            $imageFile = $form->get('image')->getData();
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename  = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
                try {
                    $imageFile->move(
                            $this->getParameter('image_directory'),
                            $newFilename
                        );
                } catch (FileException $e) {
                    $this->addFlash('error', 'Image cannot be saved.');
                }
                $blog->setImage($newFilename);
            }
            else
            {
                $blog->setImage(
                    $blogRepository->find($blog->getId())->getImage()
                );
            }

            $entityManager->persist($blog);
            $entityManager->flush();
            $this->addFlash('success', 'Blog was edited!');
            return $this->redirectToRoute('');
        }

        return $this->render('edit.html.twig', [
            'form' => $form->createView(),
            'blog' => $blog
        ]);
    }

    /**
     * @Route("/delete/{id}", name="delete")
     *
     * @param Blog                   $blog
     * @param EntityManagerInterface $em
     *
     * @return RedirectResponse
     */
    public function deleteBlog(Blog $blog, EntityManagerInterface $em): RedirectResponse
    {
        $em->remove($blog);
        $em->flush();
        $this->addFlash('success', 'Blog was deleted!');

        return $this->redirectToRoute('');
    }

    /**
     * @Route("/view/{id}", name="view")
     *
     * @return Response
     */
    public function view(Blog $blog)
    {
        return $this->render('view.html.twig', [
            'blog' => $blog
        ]);
    }
}