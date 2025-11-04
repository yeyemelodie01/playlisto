<?php

namespace App\ApiResource;

use Symfony\Component\Validator\Constraints as Assert;

final class MeInput
{
    #[Assert\Email(message: "Email must be valid")]
    #[Assert\NotBlank(message: "Email is required")]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(min: 2, max: 50, minMessage: "Username must contain at least 2 characters", maxMessage: "Username must contain at least 50 characters")]
    public ?string $username = null;

    #[Assert\Length(min: 8, minMessage: "Password must be at least 8 characters long")]
    public ?string $password = null;
}
