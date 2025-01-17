<?php

declare(strict_types=1);

namespace Rebing\GraphQL;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Contracts\View\View;

class GraphQLController extends Controller
{
    public function query(Request $request, string $schema = null): JsonResponse
    {
        $middleware = new GraphQLUploadMiddleware();
        $request = $middleware->processRequest($request);

        // If there are multiple route params we can expect that there
        // will be a schema name that has to be built

        if (Helpers::isLumen() && $request->request->count() > 1) {
            $schema = implode('/', $request->request->all());
        } elseif (! Helpers::isLumen() && $request->route()->parameters && count($request->route()->parameters) > 1) {
            $schema = implode('/', $request->route()->parameters);
        }

        if (! $schema) {
            $schema = config('graphql.default_schema');
        }

        // If a singular query was not found, it means the queries are in batch
        $isBatch = ! $request->has('query');
        $batch = $isBatch ? $request->all() : [$request->all()];

        $completedQueries = [];
        $paramsKey = config('graphql.params_key');

        $opts = [
            'context'   => $this->queryContext(),
            'schema'    => $schema,
        ];

        // Complete each query in order
        foreach ($batch as $batchItem) {
            $query = $batchItem['query'];
            $params = Arr::get($batchItem, $paramsKey);

            if (is_string($params)) {
                $params = json_decode($params, true);
            }

            $completedQueries[] = app('graphql')->query($query, $params, array_merge($opts, [
                'operationName' => Arr::get($batchItem, 'operationName'),
            ]));
        }

        $data = $isBatch ? $completedQueries : $completedQueries[0];

        $headers = config('graphql.headers', []);
        $jsonOptions = config('graphql.json_encoding_options', 0);

        return response()->json($data, 200, $headers, $jsonOptions);
    }

    protected function queryContext()
    {
        try {
            return app('auth')->user();
        } catch (Exception $e) {
            return;
        }
    }

    public function graphiql(Request $request, string $schema = null): View
    {
        $graphqlPath = '/'.config('graphql.prefix');
        if ($schema) {
            $graphqlPath .= '/'.$schema;
        }

        $view = config('graphql.graphiql.view', 'graphql::graphiql');

        return view($view, [
            'graphql_schema' => 'graphql_schema',
            'graphqlPath'    => $graphqlPath,
        ]);
    }
}
