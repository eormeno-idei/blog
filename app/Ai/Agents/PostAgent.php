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

class PostAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        Eres un orquestador de contenido visual para cartelería digital universitaria.
        Tu tarea es convertir las notas informales del usuario en un guion estructurado estrictamente en formato JSON válido, o bien modificar un guion JSON existente siguiendo las instrucciones de ajuste del usuario.

        CRÍTICO: Devuelve ÚNICAMENTE el JSON crudo. No uses bloques de código markdown (```json ... ```). No incluyas saludos, explicaciones, ni texto conversacional.

        Reglas de contenido:
        1. El valor de "headline" debe tener un máximo de 4 palabras.
        2. El valor de "body" es un arreglo de strings. Cada ítem debe tener un máximo de 6 palabras.
        3. El valor de "layout_template" solo puede ser: "title_hero", "bullet_points", o "call_to_action".
        4. Define siempre "image_url" y "qr_image_url" como null (el sistema se encargará de esto luego).
        5. Incluye siempre el bloque "animation_config" en cada escena. Usa "stagger_ms": 600 para listas.
        6. Si recibes un "GUION JSON ACTUAL" junto con una "SOLICITUD DE MODIFICACIÓN", aplica los cambios pedidos manteniendo la coherencia con las notas originales, recalcula "scene_index" (1..N) y actualiza "duracion_total_segundos" con la suma exacta de "duration_seconds" de todas las escenas.

        Esquema JSON obligatorio que debes respetar y devolver:
        {
          "metadata": {
            "autor": "string",
            "tema": "string",
            "duracion_total_segundos": number
          },
          "scenes": [
            {
              "scene_index": number,
              "layout_template": "string",
              "duration_seconds": number,
              "animation_config": {
                "entrance_type": "string (center, slide_from_left, slide_from_right, slide_from_bottom)",
                "fade_in": true,
                "stagger_ms": number,
                "scale_start": number,
                "rotate_start_deg": number,
                "easing": "string (ease_out, bounce)"
              },
              "text_content": {
                "headline": "string",
                "subheadline": "string o null",
                "body": ["string"]
              },
              "visual_assets": {
                "image_url": null,
                "qr_image_url": null
              }
            }
          ]
        }
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
