<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/api/me', name: 'api_me_')]
#[IsGranted('ROLE_USER')]
final class UserController
{
    /**
     * @param Security                    $security
     * @param UserRepository              $userRepository
     * @param UserPasswordHasherInterface $userPasswordHasher
     * @param ValidatorInterface          $validator
     */
    public function __construct(private readonly Security $security, private readonly UserRepository $userRepository, private readonly UserPasswordHasherInterface $userPasswordHasher, private readonly ValidatorInterface $validator)
    {
    }

    /**
     * @return JsonResponse
     */
    #[Route('', name: 'get', methods: ['GET'])]
    public function getUser(): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'unsupported_user_type'], 403);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ], 200);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    #[Route('/update', name: 'update', methods: ['PUT'])]
    public function updateUser(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'unsupported_user_type'], 403);
        }

        $data = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'invalid_json'], 400);
        }

        $email    = array_key_exists('email', $data) ? (string)$data['email'] : null;
        $username = array_key_exists('username', $data) ? (string)$data['username'] : null;
        $password = array_key_exists('password', $data) ? (string)$data['password'] : null;

        $constraints = new Assert\Collection([
            'email' => new Assert\Optional([
                new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180),
            ]),
            'username' => new Assert\Optional([
                new Assert\NotBlank(), new Assert\Length(min: 2, max: 50),
            ]),
            'password' => new Assert\Optional([
                new Assert\NotBlank(), new Assert\Length(min: 8, max: 4096),
            ]),
        ]);

        $violations = $this->validator->validate(
            ['email' => $email, 'username' => $username, 'password' => $password],
            $constraints
        );
        if (\count($violations) > 0) {
            $errs = [];
            foreach ($violations as $v) {
                $errs[$v->getPropertyPath()][] = $v->getMessage();
            }

            return new JsonResponse(['error' => 'validation_failed', 'violations' => $errs], 422);
        }

        if ($email !== null && $email !== $user->getEmail()) {
            $exists = $this->userRepository->findOneBy(['email' => $email]);
            if ($exists && $exists->getId() !== $user->getId()) {
                return new JsonResponse(['error' => 'email_already_used'], 409);
            }
            $user->setEmail($email);
        }

        if ($username !== null) {
            $user->setUsername($username);
        }

        if ($password !== null) {
            $user->setPassword($this->userPasswordHasher->hashPassword($user, $password));
        }

        $this->userRepository->save($user, true);

        return new JsonResponse([
            'id'       => $user->getId(),
            'email'    => $user->getEmail(),
            'username' => $user->getUsername(),
            'roles'    => $user->getRoles(),
        ], 200);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    #[Route('/delete', name: 'delete', methods: ['DELETE'])]
    public function deleteUser(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'unsupported_user_type'], 403);
        }

        $this->userRepository->remove($user, true);

        return new JsonResponse(null, 204);
    }
}
