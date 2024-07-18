# Coding Pioneers extended XHProf Tool

## Introduction

While XHProf is a great tool to analyze the performance of your site, it comes with some major limitations. The most
siginificant one is that you do not see the URL, that was called for a specific request. While this is not a problem if
you excactly know which request you just made, it is a huge problem, if you just hit save on the WordPress block editor
and 10-20 requests were made in the background.

And if that was not bad enough, you would only see that `$wpdb->query` was called 200 times and that it took 10 seconds
in total, but you would not see the actual queries that were made. Same for HTTP requests using `wp_remote_get()`

This is where our extended XHProf tool comes in. It will log all database queries and all HTTP requests made during the
request. It will also log the URL that was called and the referrer. This will help you to identify the slow requests and
the queries that are causing the performance issues.

## Note/Requirements

This toolset was developed based upon the `xhprof_prepend.php` file included in ddev. While it will also work without, I
will assume that you are using ddev in the following documentation. If you are using another environment please
contribute your documentation to the project.

For monitoring HTTP Requests the toolset uses information gathering and processed by the plugin
[Query Monitor](https://de.wordpress.org/plugins/query-monitor/), if the plugin is not active, this information will not
be available.

The toolset should also work without WordPress, but it will only provide information about start and end time, duration,
URL and referrer. It will not provide any information about database queries or HTTP requests.

## Installation

The installation expects that you are using a ddev project. If you are not using ddev, you can still use the XHProf
tool, but you will need to adjust the installation steps accordingly.

1. install your ddev project
2. copy these files into `/.ddev/xhprof/`

## Usage

1. run `ddev xhprof on`
2. If you have any other prepend file(Ninja Firewall for example) active in a .user.ini file, you must ensure this file
   require this file is included, otherwise you will not see any profiles.
3. Open the page you want to profile, or hit save within the WordPress block editor.
4. Open https://`yourproject`.ddev.site/xhprof/ in your browser, to get the default XHProf output.
5. To access the additional information run `ddev ssh` to connect to your ddev container and run `cd /tmp/xhprof`
6. Inside this directory you will find our additional log files as explained below

## Configuration

Out of the box you should not need any configuration. There are two options how you can configure the toolset.

### Using .env variables

All settings will by default try to read the value from the .env file. It is recommended to use this method, as you do
not have to patch this file.

### Using the xhprof_prepend.php file

Between the comment "Customize these values as needed" and "That's all there is to configure", you will find all
configurable settings.

#### Env: CP_XHPROF_WPDB_MIN_QUERY_TIME / Const: WPDB_MIN_QUERY_TIME

Default value: `0.1` time in seconds

By changing this you can set the threshold in seconds for the database queries that will be logged. If you set this to
0, all queries will be logged. It is recommended to set this to a value between 0.1 and 0.5, to ensure you only focus on
the slow queries.

#### Env: CP_XHPROF_LOG_LOCATION / Const: LOG_LOCATION

Default value: `/tmp/xhprof/`

The location where the log files will be stored. This should be a location that is writable by the webserver user. The
default value is taken from ddev, only change this if you have an alternative configuration.

#### Env: CP_XHPROF_EMPTY_LOGS / Const: EMPTY_LOGS

Default value: `skip`

This setting will determine what will happen if a DB or HTTP log would be empty. `skip` will prevent the log file from
being created, `log` or currently any other value will create the log file with a message that no queries were found.

#### Env: CP_XHPROF_LIB_PATH / Const: XHPROF_LIB_PATH

Default value: `/var/xhprof/xhprof_lib/utils/xhprof_lib.php`

This is the path to the `xhprof_lib.php file`. It should not be changed unless you have a different setup.

#### Env: CP_XHPROF_RUNS_PATH / Const: XHPROF_RUNS_PATH

Default value: `/var/xhprof/xhprof_lib/utils/xhprof_runs.php`

This is the path to the `xhprof_runs.php file`. It should not be changed unless you have a different setup.

#### Env: CP_XHPROF_HTTP_LOG_SUFFIX / Const: HTTP_LOG_SUFFIX

Default value: `_http.log`

This is the suffix for the HTTP log files. By using the same value for HTTP and DB logs, you will have both logs in the
same file.

#### Env: CP_XHPROF_DB_LOG_SUFFIX / Const: DB_LOG_SUFFIX

Default value: `_db.log`

This is the suffix for the DB log files. By using the same value for HTTP and DB logs, you will have both logs in the
same file.

#### Env: CP_XHPROF_CLI_BASE_LINK / Const: CLI_BASE_LINK

Default value: `(empty)`

This is the base link for the XHProf profile URL, it is recommended to configure this, otherwise you will only see
relative URLs in your xhprof.log file.

#### Env: CP_XHPROF_RELATIVE_PATH / Const: XHPROF_RELATIVE_PATH

Default value: `/xhprof/`

This is the relative path to the XHProf output. It should not be changed unless you have a different setup.

## Additional log files

In addition to the default XHProf output, we have added some additional log files to help you analyze the performance of
your site.
[HEXHASH] is a hash of the XHProf profile URL, which I will use below to refer to the different files.

### xhprof.log

This file contains a summary of the access URLs with some basic performance data. It is useful to get an overview of the
performance of your site.

The given information should basically provide you with most information you need to see which requests you will need to
monitor in more detail.

Example:

```
[Start date & time - End time] ∆ [Duration] | [XHProf profile URL]                                         | URI: [Accessed URL]              | [GET/POST/CLI]([Number of vars], [Size in kb]) | Referrer: [Referrer]
[2024-07-16 06:16:20 - 06:16:20] ∆ 0.12 s | https://bonedo.ddev.site/xhprof/?run=669610344cbf5&source=ddev | URI: /wp/wp-admin/admin-ajax.php | POST(5 vars, 0.1 kb) | Referrer: /wp/wp-admin/users.php
```

For CLI requests some information will be empty.

### [HEXHASH]_db.log

This file contains all database queries for the specific request, optionally all requests slower than
a [query threshold](#env-cp_xhprof_wpdb_min_query_time--const-wpdb_min_query_time)
that you can configure as described above.

It will also contain some self-explanatory information about all queries.

It will only contain queries that used the `wpdb` object. It will NOT contain any other queries accessing the database
via any other means.

This file might be missing if no queries were made during the request and the configuration is set
to [skip empty logs](#env-cp_xhprof_wpdb_min_query_time--const-wpdb_min_query_time).

### [HEXHASH]_http.log

This file requires the plugin [Query Monitor](https://de.wordpress.org/plugins/query-monitor/) to be installed and
active.

It contains all outgoing HTTP requests using WordPress Core HTTP Request API with request parameter for the specific
request.

It will NOT contain custom CURL requests or other HTTP requests.

This file might be missing if no HTTP requests were made during the request and the configuration is set
to [skip empty logs](#env-cp_xhprof_wpdb_min_query_time--const-wpdb_min_query_time).

If Query Monitor is not present, these files will never be created.

### [HEXHASH].ddev.xhprof (default)

This file contains raw information parsed by XHProf, if you call `/xhprof/`.

## Credits

This toolset was developed by the Coding Pioneers team. Please feel free to contribute to this project on our [GitHub page](https://github.com/coding-pioneers/xhprof-toolset) or visit our [website](https://coding-pioneers.com) for more information.

The XHProf tool was originally developed by Facebook 

## License

This project is licensed under the Apache License 2.0 - see the [LICENSE](LICENSE) file for details.

Originally developed at Facebook, XHProf was open sourced in Mar, 2009.

XHProf is now maintained by [Phacility](https://github.com/phacility/xhprof).
