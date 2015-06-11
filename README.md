PhpBench
========

[![Build Status](https://travis-ci.org/phpbench/phpbench.svg?branch=master)](https://travis-ci.org/phpbench/phpbench)

PhpBench is a benchmarking framework for PHP.

Features:

- Generate reports
- Records memory statistics
- Run iterations in separate processes
- Support for parameterized benchmarking cases and matrixes
- Per-project configuration
- Dump benchmark results as XML and generate reports later
- Run one or more (setup) parameterized methods before the benchmark
- Nice command line interface
- Add custom report generators
- Add custom progress loggers
- More

**DISCLAIMER**: This library is in an *ALPHA* stage and should not be
considered stable. Contributions welcome :)

Why?
----

There already exists other benchmarking frameworks, why another?

All of the existing frameworks (as far as I can see) are designed for
benchmarking algorithms or other relatively quick scenarios. They are the
equivalent of "unit" tests.

PhpBench is designed also for running *BIG* benchmark suites which may take serveral
minutes to complete, it could be seen as a *system* benchmarking framework,
and therefore analagous to integration testing.

PhpBench also provides a powerful report generation capability thanks to the
[Cellular](https://github.com/phpbench/cellular) library.

Installation
------------

Install with composer, add it to `composer.json`:

````javascript
{
    "phpbench/phpbench": "~1.0@dev"
}
````

How it works
------------

PhpBench is inspired by [PHPUnit](https://github.com/phpunit/phpunit).
Basically, you create a class. Each method which begins with `bench` is
executed by the benchmark runner and the time it took to execute it is
recorded.

The class name must end with `Bench` (this is just to optimize finding
benchmark files).

The method is known as the benchmark *subject* and each method optionally
accepts an `Iteration` from which can be accessed contextual information.

You can annotate **both** the class and the subject methods with any of the 
following annotations:

### @description

**singular**

A description of the benchmark subject.

### @group

**plural**

Assign the annotated method or class instance in the named group.

### @revs

**plural**

Number of times the subject should be *consecutively* executed within a single
iteration. Use this for measuring the speed of things at the *microsecond*
level. You can declare this multiple times.

### @iterations

**singular**

Define the number of iterations that should be *measured*. The difference
between this and `@revs` is that revs happen in a single measurement, whereas
each iteration is recorded separately. 

This can be useful for seeing how well larger operations scale on consecutive
calls, it is not a good way to measure things that happen at the microsecond
level however, where you will want to perform tens of thousands of repetitions
as the time is measured per repetition so the results will be at a lower
resolution.

### @beforeMethod

**plural**

Specify a method which should be executed before the subject. The before
method also accepts an `Iteration` object and has access to the iteration
context.

Multiple before methods can be specified.

### @paramProvider

**plural**

Specify a method which will provide parameters which can be accessed from the
`Iteration` object by both the before method and the subject method.

If multiple parameter providers are specified, then the they will be combined
in to a cartesian product.

### @processIsolation

**singular**

Run each iteration or each set of iterations in an isolated process. This is
useful for seeing the initial cost of the revolution.

Must be one of `iteration` or `iterations`.

setUp and tearDown
------------------

You can defined `setUp` and `tearDown` methods. These will be called before
and after the subject resepectively.

These methods are useful for establishing an external state prior to running
benchmarks.

NOTE: They cannot be used to establish "internal" state when process isolation is
used.

Reports
-------

Reports can be specified simply as:

````bash
$ php vendor/bin/phpbench run benchmarks/ --report=<report name>
````

But you can also pass configuration to the report:

````bash
$ php vendor/bin/phpbench run benchmarks/ --report='{"name": "console_table", "cols": ["time", "memory", "deviation"]}'
````

As you can see we pass a JSON encoded string which represents the report
configuration. This string **MUST** contain at least the `name` key indicating
which report to use, all other keys are interpreted as options.

There is a single report included by default: `console_table`.

It has the following options:

- `time_format`: Either `fraction` (of a second) or `integer` (number of microseconds).
- `precision`: Number of decimal places to use when showing the time as a fraction.
- `cols`: Choose which columns to display (defaults to all), columns are
  defined below.
- `aggregate`: Aggregate the benchmark data, values: `none` or `run` or
  `subject`.
- `sort`: Sort the data by the given column.
- `sort_dir`: The sort direction (one of `asc`, `desc`).
- `group`: Only report on the specified group.
- `style`: Display one table per data group (`horizontal`) or one table per row (`vertical`) In the
  latter case the table will have two columns, `field` and `value`. Defaults
  to `horizontal`.

The columns are:

- `run`: Show the run index.
- `iter`: Show the iteration index.
- `time`: Time taken in microseconds (aggregate)
- `memory`: Memory used by the subject (aggregate)
- `memory_diff`: Memory used by the subject (aggregate)
- `revs`: Number of times the subject was repeated (aggregate)
- `rps`: Revolutions per second - number of times the subject is executed in a second (aggregate)
- `deviation_[mean|min|max]`: Deviation from the mean as a percentage.
  Deviation value is a percentage from the result of the function sufix of the
  option name.

The `aggregate` option, when specified will make available a new set of column
names. Each `aggregate` column above will be prefixed with a function, e.g.
`mean_memory`, `max_rps`.

To see (readable) list of all the available column names, run your report with
the `vertical` style, or, you can just specify a non-existing column and an
Exception will splurge all the available columns onto your screen.

Dumping XML and deferring reports
---------------------------------

Benchmark suites could take an unreasonable amount of time to compete, reports
on the other hand are created in milliseconds. Therefore debugging or tuning
reports could be very tedious indeed.

PhpBench allows you to dump an XML representation of the results from which
reports can be generated:

````bash
$ php vendor/bin/phpbench run path/to/benchmarks --dump-file=results.xml
````

And then you can generate the reports using:

````bash
$ php vendor/bin/phpbench report resuts.xml --report=console_table
````

This is also a great way to compare benchmarks, for example accross git
branches.

Example
-------

````php
<?php
use PhpBench\Benchmark\Iteration;
use PhpBench\Benchmark;

/**
 * Annotations in the class doc are applied to each method
 *
 * @revs 1000
 */
class SomeBenchmarkBench implements Benchmark
{
    /**
     * @description randomBench
     */
    public function benchRandom(Iteration $iteration)
    {
        usleep(rand(0, 50000));
    }

    /**
     * @iterations 3
     * @description Do nothing three times
     */
    public function benchDoNothing(Iteration $iteration)
    {
    }

    /**
     * @description Each iteration will be in an isolated process
     * @processIsolation iteration
     */
    public function benchSomethingIsolated()

    /**
     * @paramProvider provideParamsOne
     * @paramProvider provideParamsTwo
     * @description Parameterized bench mark
     * @iterations 1
     */
    public function benchParameterized(Iteration $iteration)
    {
        // do something with a parameter
        $param = $iteration->getParameter('length');
    }

    public function provideParamsOne()
    {
        return array(
            array('length' => '1'),
            array('length' => '2'),
        );
    }

    public function provideParamsTwo()
    {
        return array(
            array('strategy' => 'left'),
            array('strategy' => 'right'),
        );
    }
}
````

You can then run the benchmark:

````bash
$ php bin/phpbench run tests/Functional/benchmarks --report=console_table
PhpBench 0.2. Running benchmarks.

......

Done (6 subjects, 26 iterations) in 0.28s

  BenchmarkBench->benchRandom: randomBench
  
  +-----+------+--------+-----------+------+--------+-------------+-------+---------------+----------------+---------------+---------------+------------------+
  | run | iter | params | time      | revs | memory | memory_diff | rps   | deviation_sum | deviation_mean | deviation_min | deviation_max | deviation_median |  
  +-----+------+--------+-----------+------+--------+-------------+-------+---------------+----------------+---------------+---------------+------------------+
  | 0   | 0    | []     | 0.028350s | 1    | 288b   | 288b        | 35.27 | 0.00%         | 0.00%          | 0.00%         | 0.00%         | 0.00%            |  
  +-----+------+--------+-----------+------+--------+-------------+-------+---------------+----------------+---------------+---------------+------------------+
  
  BenchmarkBench->benchDoNothing: Do nothing three times
  
  +-----+------+--------+-----------+------+--------+-------------+------------+---------------+----------------+---------------+---------------+------------------+
  | run | iter | params | time      | revs | memory | memory_diff | rps        | deviation_sum | deviation_mean | deviation_min | deviation_max | deviation_median |  
  +-----+------+--------+-----------+------+--------+-------------+------------+---------------+----------------+---------------+---------------+------------------+
  | 0   | 0    | []     | 0.000006s | 1    | 192b   | 192b        | 166,666.67 | -99.92%       | -99.52%        | +50.00%       | -99.76%       | -99.51%          |  
  | 0   | 1    | []     | 0.002463s | 1000 | 384b   | 192b        | 406,008.93 | -67.05%       | +97.70%        | +61,475.00%   | -2.76%        | +99.51%          |  
  | 0   | 2    | []     | 0.000004s | 1    | 576b   | 192b        | 250,000.00 | -99.95%       | -99.68%        | 0.00%         | -99.84%       | -99.68%          |  
  | 0   | 3    | []     | 0.002533s | 1000 | 768b   | 192b        | 394,788.79 | -66.11%       | +103.32%       | +63,225.00%   | 0.00%         | +105.18%         |  
  | 0   | 4    | []     | 0.000004s | 1    | 960b   | 192b        | 250,000.00 | -99.95%       | -99.68%        | 0.00%         | -99.84%       | -99.68%          |  
  | 0   | 5    | []     | 0.002465s | 1000 | 1,152b | 192b        | 405,679.51 | -67.02%       | +97.86%        | +61,525.00%   | -2.68%        | +99.68%          |  
  +-----+------+--------+-----------+------+--------+-------------+------------+---------------+----------------+---------------+---------------+------------------+
````

Here we used the default configuration for `console_table`. See above for
details on how to configure reports (and restrict the column output).

Configuration
-------------

PhpBench supports configuration files, allowing you to configure and extend
your reports.

Configuration files are in plain PHP and MUST return a
`Phpbench\Configuration` object and bootstrap the autoloader.

Phpbench will first look for the file `.phpbench` and then `.phpbench.dist`.
It is also possible to specify a configuration file with the ``--config``
option.

Below is the most minimal example:

````php
<?php
// .phpbench

require(__DIR__ . '/vendor/autoload.php');

$configuration = new PhpBench\Configuration();

return $configuraton;
````

And here is a full example:

````php
<?php
// .phpbench

require(__DIR__ . '/vendor/autoload.php');
$configuration = new PhpBench\Configuration();

// set the path in which to search for benchmarks
$configuration->setPath(__DIR__ . '/benchmarks');

// add a new report generator
$configuration->addReportGenerator('my_report_generator', new MyReportGenerator());

// add a report
$configuration->addReport(array(
    'name' => 'my_report_generator',
    'option_1' => 'one',
    'option_2' => 'two',
));

return $configuraton;
````

See also
--------

This library was influenced by the
[athletic](https://github.com/polyfractal/athletic) benchmarking framework.
