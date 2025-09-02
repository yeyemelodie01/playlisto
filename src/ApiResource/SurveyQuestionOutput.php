<?php

namespace App\ApiResource;

use Symfony\Component\Serializer\Annotation\Groups;

final class SurveyQuestionOutput
{
    #[Groups(['questionnaire:read'])]
    public int $id;

    /** single_choice | multiple_choice | scale | text */
    #[Groups(['questionnaire:read'])]
    public string $type;

    #[Groups(['questionnaire:read'])]
    public string $label;

    /** @var SurveyOptionOutput[] */
    #[Groups(['questionnaire:read'])]
    public array $options = [];

    public function __construct(int $id, string $type, string $label, array $options = [])
    {
        $this->id = $id;
        $this->type = $type;
        $this->label = $label;
        $this->options = $options;
    }
}
