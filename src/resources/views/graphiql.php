<!DOCTYPE html>
<html>
    <head>
        <style>
            html, body {
                height: 100%;
                margin: 0;
                padding: 0;
                width: 100%;
                overflow: hidden;
            }
            #graphiql {
                height: 100vh;
            }
        </style>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/graphiql/0.10.2/graphiql.min.css" />
        <script src="https://cdnjs.cloudflare.com/ajax/libs/fetch/2.0.3/fetch.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/react/15.5.4/react.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/react/15.5.4/react-dom.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/graphiql/0.11.11/graphiql.min.js"></script>
    </head>
    <body>
        <div id="graphiql">Loading...</div>
        <script>
            /**
            * This GraphiQL example illustrates how to use some of GraphiQL's props
            * in order to enable reading and updating the URL parameters, making
            * link sharing of queries a little bit easier.
            *
            * This is only one example of this kind of feature, GraphiQL exposes
            * various React params to enable interesting integrations.
            */

            // Parse the search string to get url parameters.
            var search = window.location.search;
            var parameters = {};
            search.substr(1).split('&').forEach(function (entry) {
                var eq = entry.indexOf('=');
                if (eq >= 0) {
                    parameters[decodeURIComponent(entry.slice(0, eq))] =
                    decodeURIComponent(entry.slice(eq + 1));
                }
            });

            // if variables was provided, try to format it.
            if (parameters.variables) {
                try {
                    parameters.variables =
                    JSON.stringify(JSON.parse(parameters.variables), null, 2);
                } catch (e) {
                    // Do nothing, we want to display the invalid JSON as a string, rather
                    // than present an error.
                }
            }

            // When the query and variables string is edited, update the URL bar so
            // that it can be easily shared
            function onEditQuery(newQuery) {
                parameters.query = newQuery;
                updateURL();
            }

            function onEditVariables(newVariables) {
                parameters.variables = newVariables;
                updateURL();
            }

            function onEditOperationName(newOperationName) {
                parameters.operationName = newOperationName;
                updateURL();
            }

            function updateURL() {
                var newSearch = '?' + Object.keys(parameters).filter(function (key) {
                    return Boolean(parameters[key]);
                }).map(function (key) {
                    return encodeURIComponent(key) + '=' + encodeURIComponent(parameters[key]);
                }).join('&');
                history.replaceState(null, null, newSearch);
            }

            var xcrsfToken = null;

            // Defines a GraphQL fetcher using the fetch API.
            function graphQLFetcher(graphQLParams) {
                return fetch('<?php echo $graphqlPath; ?>', {
                    method: 'post',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': xcrsfToken || '<?php echo csrf_token(); ?>'
                    },
                    body: JSON.stringify(graphQLParams),
                    credentials: 'include',
                }).then(function (response) {
                    xcrsfToken = response.headers.get('x-csrf-token');

                    return response.text();
                }).then(function (responseBody) {
                    try {
                        return JSON.parse(responseBody);
                    } catch (error) {
                        return responseBody;
                    }
                });
            }

            // Render <GraphiQL /> into the body.
            ReactDOM.render(
                React.createElement(GraphiQL, {
                    fetcher: graphQLFetcher,
                    query: parameters.query,
                    variables: parameters.variables,
                    operationName: parameters.operationName,
                    onEditQuery: onEditQuery,
                    onEditVariables: onEditVariables,
                    onEditOperationName: onEditOperationName
                }),
                document.getElementById('graphiql')
            );
        </script>
    </body>
</html>
