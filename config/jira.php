<?php

return [
    'username' => env('JIRA_USERNAME'),
    'token' => env('JIRA_TOKEN'),
    'url' => env('JIRA_HOST', 'https://sevima.atlassian.net/rest/api/2')
];