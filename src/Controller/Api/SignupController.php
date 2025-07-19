<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Message\SendWelcomeMessage;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SignupController
{
    /**
     * @param UserRepository              $userRepository
     * @param UserPasswordHasherInterface $passwordHasher
     * @param MessageBusInterface         $messageBus
     * @param ValidatorInterface          $validator
     * @param LoggerInterface             $logger
     */
    public function __construct(private UserRepository $userRepository, private UserPasswordHasherInterface $passwordHasher, private MessageBusInterface $messageBus, private ValidatorInterface $validator, private LoggerInterface $logger)
    {
    }

    /**
     * Handles user registration and dispatches a welcome email.
     *
     * @param Request $request the incoming HTTP request containing user data
     *
     * @return JsonResponse a JSON response indicating success or failure
     *
     * @throws ExceptionInterface
     */
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
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $password)
            );

            $this->userRepository->save($user, true);

            // Dispatch a message to send a welcome email
            $this->messageBus->dispatch(new SendWelcomeMessage($email, $username));

            return new JsonResponse(['message' => 'User created successfully'], 201);
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

    /**
     * Validates the user input using Symfony Validator component.
     *
     * @param string|null $username the username provided by the user
     * @param string|null $email    the email provided by the user
     * @param string|null $password the password provided by the user
     *
     * @return array<string, string> an array of validation error messages, or an empty array if valid
     */
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
