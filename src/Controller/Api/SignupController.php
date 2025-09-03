<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class SignupController
{
    public function __construct(private UserRepository $userRepository, private UserPasswordHasherInterface $passwordHasher, private ValidatorInterface $validator, private LoggerInterface $logger)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $username = $data['username'] ?? null;
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;

            // Validate the input data
            $errors = $this->validateInput($username, $email, $password);
            if (count($errors) > 0) {
                return new JsonResponse(['error' => 'Validation failed', 'details' => $errors], 400);
            }

            // Create and save the new user
            $user = new User();
            $user->setUsername($username);
            $user->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));

            // Persist the user to the database
            $this->userRepository->save($user);


            return new JsonResponse(['message' => 'User registered successfully'], 201);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create user or send welcome email.', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'error' => 'An unexpected error occurred.',
                'details' => 'Internal server error',
            ], 500);
        }
    }

    private function validateInput(?string $username, ?string $email, ?string $password): array
    {
        $constraints = new Assert\Collection([
            'username' => [new Assert\NotBlank(), new Assert\Length(['min' => 3, 'max' => 50])],
            'email' => [new Assert\NotBlank(), new Assert\Email()],
            'password' => [new Assert\NotBlank(), new Assert\Length(['min' => 6])],
        ]);

        $input = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ];

        $violations = $this->validator->validate($input, $constraints);
        $errors = [];

        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return $errors;
    }
}
