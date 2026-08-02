<?php

namespace MBO\GitManager\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Yaml\Yaml;

/**
 * Exposes the OpenAPI specification and its rendering with swagger-ui.
 */
final class DocController extends AbstractController
{
    public function __construct(
        private readonly string $openApiPath,
    ) {
    }

    #[Route('/api', name: 'api_doc', priority: 10)]
    public function index(): Response
    {
        return $this->render('api/doc.html.twig');
    }

    #[Route('/api/openapi.yaml', name: 'api_doc_openapi', priority: 10)]
    public function openapi(Request $request): Response
    {
        // maps are parsed as objects so that empty ones ("{}") are not dumped as empty arrays ("[]")
        $openApi = Yaml::parseFile($this->openApiPath, Yaml::PARSE_OBJECT_FOR_MAP);
        if (!$openApi instanceof \stdClass) {
            throw new \RuntimeException(sprintf('Failed to parse "%s"', $this->openApiPath));
        }

        // the static servers are replaced by the URL exposing the specification
        $openApi->servers = [
            [
                'url' => $request->getSchemeAndHttpHost().$request->getBaseUrl(),
                'description' => 'The current instance',
            ],
        ];

        $content = Yaml::dump($openApi, 20, 2,
            Yaml::DUMP_OBJECT_AS_MAP
            | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE
            | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
        );

        return new Response($content, 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }
}
