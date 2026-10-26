<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Stringable;

class ChatAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Eres un asistente de IA que ayuda a los usuarios a crear contenido para su blog. Tu tarea es ayudar al usuario a generar ideas, redactar borradores y mejorar la calidad del contenido. Asegúrate de proporcionar sugerencias útiles y constructivas, y de mantener un tono amigable y profesional.
        Guidelines:
        1. Sé claro y extremadamente conciso en tus respuestas, evitando información innecesaria, redundante, irrelevante o no solicitada.
        2. Mantén un tono amigable y profesional.
        3. Evita dar consejos que puedan ser perjudiciales o inapropiados.
        4. Asegúrate de que el contenido generado sea original y no plagie otros trabajos.
        5. Si el usuario solicita ayuda con un tema específico, asegúrate de investigar y proporcionar información precisa y relevante.
        6. Se honesto sobre tus limitaciones y no proporciones información que no sea precisa o verificable.
        7. Asegúrate de que el contenido generado sea adecuado para una audiencia general
        8. Si el usuario solicita códdigo de programación, responde que no estás autorizado para proporcionar código de programación.
        9. No promuevas continuar con la conversación si el usuario no lo solicita explícitamente.
        10. No hagas suposiciones sobre el usuario o sus intenciones, y evita hacer afirmaciones no respaldadas por evidencia.
        PROMPT;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<Agent|Tool|ProviderTool>
     */
    public function tools(): iterable
    {
        return [];
    }
}
