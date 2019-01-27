<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\ValidatedFieldDefinition;

$authors = [
    1 => [
        'id' => 1,
        'name'=> 'Cormac McCarthy',
        'deceased' => false
    ],
    2 => [
        'id' => 2,
        'name' => 'J.D.Salinger',
        'deceased' => true
    ],
];

class AuthorType extends ObjectType {
    public function __construct()
    {
        parent::__construct([
            'fields' => [
                'id' => [
                    'type' => Type::id(),
                    'resolve' => function($author) {
                        return $author['id'];
                    }
                ],
                'name' => [
                    'type' => Type::string(),
                    'resolve' => function($author) {
                        return $author['name'];
                    }
                ]
            ]
        ]);
    }
}

$authorType = new AuthorType();

try {
    $mutationType = new ObjectType([
        'name' => 'Mutation',
        'fields' => [
            'updateAuthor' => new ValidatedFieldDefinition([
                'name' => 'updateAuthor',
                'type' => $authorType,
                'args' => [
                    'authorId' => [
                        'type' => Type::id(),
                        'errorCodes' => [
                            'unknownAuthor',
                            'deceasedAuthor'
                        ],
                        'validate' => function(string $authorId) use ($authors) {
                            if (!isset($authors[$authorId])) {
                                return ['unknownAuthor', "We have no record of that author"];
                            }

                            if ($authors[$authorId]['deceased']) {
                                return ['unknownAuthor', "Please choose a living author"];
                            }

                            return 0;
                        }
                    ],
                ],
                'resolve' => function ($value, $args) use ($authors) {
                    $authorId = $args['authorId'];

                    // AuthorProvider::update($authorId);
                    return $authors[$authorId];
                },
            ]),
        ],
    ]);

    $queryType = new ObjectType([
        'name'=>'Query',
        'fields'=>[
            'author'=> [
                'type' => $authorType,
                "args" => [
                    'authorId' => [
                        'type' => Type::id()
                    ]
                ],
                'resolve' => function($value, $args) use ($authors) {
                    return $authors[$args['authorId']];
                }
            ]
        ]
    ]);

    $schema = new Schema([
        'mutation' => $mutationType,
        'query' => $queryType
    ]);


    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    $query = $input['query'];
    $variableValues = isset($input['variables']) ? $input['variables'] : null;
    $result = GraphQL::executeQuery($schema, $query, null, null, $variableValues);
    $output = $result->toArray();
} catch (\Exception $e) {
    $output = [
        'error' => [
            'message' => $e->getMessage()
        ]
    ];
}
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($output);
