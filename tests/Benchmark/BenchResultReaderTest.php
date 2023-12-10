<?php

declare(strict_types=1);

/*
 * This file is part of the box project.
 *
 * (c) Kevin Herrera <kevin@herrera.io>
 *     Théo Fidry <theo.fidry@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace KevinGH\Box\Benchmark;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(BenchResultReader::class)]
final class BenchResultReaderTest extends TestCase
{
    #[DataProvider('xmlProvider')]
    public function test_it_can_read_parameter_set_mean_time(
        string $xml,
        array $expected,
    ): void {
        $actual = BenchResultReader::readMeanTimes($xml);

        self::assertSame($expected, $actual);
    }

    public static function xmlProvider(): iterable
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <phpbench xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" version="1.2.14">
                <suite tag="" context="" date="2023-11-24T13:24:26+00:00"
                       config-path="/Users/tfidry/Project/Humbug/box/phpbench.json" uuid="134b3d43853727d7d7c3fa88fa8580ad175af61a">
                    <env>
                        <uname>
                            <value name="os" type="string">Darwin</value>
                            <value name="host" type="string">Theos-MacBook-Pro-3.local</value>
                            <value name="release" type="string">22.6.0</value>
                            <value name="version" type="string">Darwin Kernel Version 22.6.0: Wed Jul 5 22:22:52 PDT 2023;
                                root:xnu-8796.141.3~6/RELEASE_ARM64_T8103
                            </value>
                            <value name="machine" type="string">arm64</value>
                        </uname>
                        <php>
                            <value name="xdebug" type="boolean"></value>
                            <value name="version" type="string">8.2.1</value>
                            <value name="ini" type="string">/Users/tfidry/.phpbrew/php/php-8.2.1/etc/cli/php.ini</value>
                            <value name="extensions" type="string">Core, date, libxml, openssl, pcre, zlib, bcmath, bz2, calendar,
                                ctype, curl, dom, fileinfo, filter, hash, iconv, intl, json, mbstring, SPL, session, pcntl,
                                standard, mysqlnd, PDO, pdo_mysql, pdo_pgsql, pgsql, Phar, posix, random, readline, Reflection,
                                mysqli, shmop, SimpleXML, sockets, sodium, sysvmsg, sysvsem, sysvshm, tokenizer, xml, xmlreader,
                                xmlwriter, xsl, zip, blackfire, exif, gd, mongodb, pdo_sqlite, redis, soap
                            </value>
                        </php>
                        <opcache>
                            <value name="extension_loaded" type="boolean"></value>
                            <value name="enabled" type="boolean"></value>
                        </opcache>
                        <vcs>
                            <value name="system" type="string">git</value>
                            <value name="branch" type="string">fix/phpbench</value>
                            <value name="version" type="string">21c9a64d31aedb9e2826be7caec9c37a3589fd15</value>
                        </vcs>
                        <sampler>
                            <value name="nothing" type="double">0.010013580322266</value>
                            <value name="md5" type="double">0.16617774963379</value>
                            <value name="file_rw" type="double">2.0968914031982</value>
                        </sampler>
                    </env>
                    <benchmark class="\KevinGH\Box\Benchmark\CompileBench">
                        <subject name="bench">
                            <executor name="remote">
                                <parameter name="php_config" type="collection"/>
                                <parameter name="safe_parameters" value="1" type="boolean"/>
                                <parameter name="executor" value="remote" type="string"/>
                            </executor>
                            <variant sleep="0" output-time-unit="microseconds" output-time-precision="" output-mode="time" revs="1"
                                     warmup="0" retry-threshold="">
                                <parameter-set name="no compactors">
                                    <parameter name="0"
                                               value="/Users/tfidry/Project/Humbug/box/tests/Benchmark/../../fixtures/bench/without-compactors"
                                               type="string"/>
                                    <parameter name="1" value="" type="boolean"/>
                                </parameter-set>
                                <iteration time-net="2997739" time-revs="1" time-avg="2997739" mem-peak="44814376"
                                           mem-real="46137344" mem-final="13907288" comp-z-value="2.8717950681752"
                                           comp-deviation="13.623163071243"/>
                                <iteration time-net="2668026" time-revs="1" time-avg="2668026" mem-peak="44814376"
                                           mem-real="46137344" mem-final="13907288" comp-z-value="0.2373774742891"
                                           comp-deviation="1.1260664375105"/>
                                <iteration time-net="2650732" time-revs="1" time-avg="2650732" mem-peak="44814376"
                                           mem-real="46137344" mem-final="13907288" comp-z-value="0.09919785180328"
                                           comp-deviation="0.47057275305226"/>
                                <iteration time-net="2600202" time-revs="1" time-avg="2600202" mem-peak="44814376"
                                           mem-real="46137344" mem-final="13907288" comp-z-value="-0.30453849168049"
                                           comp-deviation="-1.4446635066721"/>
                                <iteration time-net="2559712" time-revs="1" time-avg="2559712" mem-peak="44814376"
                                           mem-real="46137344" mem-final="13907288" comp-z-value="-0.62805490861415"
                                           comp-deviation="-2.9793541094079"/>
                                <iteration time-net="2564224" time-revs="1" time-avg="2564224" mem-peak="44814376"
                                           mem-real="46137344" mem-final="13907288" comp-z-value="-0.59200388186175"
                                           comp-deviation="-2.8083359814864"/>
                                <iteration time-net="2556542" time-revs="1" time-avg="2556542" mem-peak="44814376"
                                           mem-real="46137344" mem-final="13907288" comp-z-value="-0.65338331171812"
                                           comp-deviation="-3.0995064732181"/>
                                <iteration time-net="2573044" time-revs="1" time-avg="2573044" mem-peak="44814376"
                                           mem-real="46137344" mem-final="13907288" comp-z-value="-0.52153179499203"
                                           comp-deviation="-2.4740319282354"/>
                                <iteration time-net="2619165" time-revs="1" time-avg="2619165" mem-peak="44814376"
                                           mem-real="46137344" mem-final="13907288" comp-z-value="-0.15302350491059"
                                           comp-deviation="-0.72590979218265"/>
                                <iteration time-net="2593782" time-revs="1" time-avg="2593782" mem-peak="44814376"
                                           mem-real="46137344" mem-final="13907288" comp-z-value="-0.35583450049042"
                                           comp-deviation="-1.6880004706031"/>
                                <stats max="2997739" mean="2638316.8" min="2556542" mode="2595394.9647749" rstdev="4.7437796736309"
                                       stdev="125155.93608439" sum="26383168" variance="15664008337.16"/>
                            </variant>
                            <variant sleep="0" output-time-unit="microseconds" output-time-precision="" output-mode="time" revs="1"
                                     warmup="0" retry-threshold="">
                                <parameter-set name="with compactors; no parallel processing">
                                    <parameter name="0"
                                               value="/Users/tfidry/Project/Humbug/box/tests/Benchmark/../../fixtures/bench/with-compactors"
                                               type="string"/>
                                    <parameter name="1" value="" type="boolean"/>
                                </parameter-set>
                                <iteration time-net="2841721" time-revs="1" time-avg="2841721" mem-peak="44786680"
                                           mem-real="46137344" mem-final="13898824" comp-z-value="2.7689260245084"
                                           comp-deviation="8.7605877843056"/>
                                <iteration time-net="2577806" time-revs="1" time-avg="2577806" mem-peak="44786680"
                                           mem-real="46137344" mem-final="13898824" comp-z-value="-0.42358416016785"
                                           comp-deviation="-1.3401752832493"/>
                                <iteration time-net="2548269" time-revs="1" time-avg="2548269" mem-peak="44786680"
                                           mem-real="46137344" mem-final="13898824" comp-z-value="-0.78088546295391"
                                           comp-deviation="-2.470638647311"/>
                                <iteration time-net="2632725" time-revs="1" time-avg="2632725" mem-peak="44786680"
                                           mem-real="46137344" mem-final="13898824" comp-z-value="0.24075650569895"
                                           comp-deviation="0.76172800723081"/>
                                <iteration time-net="2570261" time-revs="1" time-avg="2570261" mem-peak="44786680"
                                           mem-real="46137344" mem-final="13898824" comp-z-value="-0.51485403623925"
                                           comp-deviation="-1.6289434750712"/>
                                <iteration time-net="2555397" time-revs="1" time-avg="2555397" mem-peak="44786680"
                                           mem-real="46137344" mem-final="13898824" comp-z-value="-0.69465992595764"
                                           comp-deviation="-2.1978302084367"/>
                                <iteration time-net="2562273" time-revs="1" time-avg="2562273" mem-peak="44786680"
                                           mem-real="46137344" mem-final="13898824" comp-z-value="-0.61148276653193"
                                           comp-deviation="-1.9346665123508"/>
                                <iteration time-net="2592216" time-revs="1" time-avg="2592216" mem-peak="44786680"
                                           mem-real="46137344" mem-final="13898824" comp-z-value="-0.24927018877106"
                                           comp-deviation="-0.78866439601865"/>
                                <iteration time-net="2593569" time-revs="1" time-avg="2593569" mem-peak="44786680"
                                           mem-real="46137344" mem-final="13898824" comp-z-value="-0.2329033044338"
                                           comp-deviation="-0.73688131271379"/>
                                <iteration time-net="2653987" time-revs="1" time-avg="2653987" mem-peak="44786680"
                                           mem-real="46137344" mem-final="13898824" comp-z-value="0.49795731484806"
                                           comp-deviation="1.5754840436151"/>
                                <stats max="2841721" mean="2612822.4" min="2548269" mode="2582150.9334638" rstdev="3.1638937648617"
                                       stdev="82666.92500051" sum="26128224" variance="6833820489.04"/>
                            </variant>
                            <variant sleep="0" output-time-unit="microseconds" output-time-precision="" output-mode="time" revs="1"
                                     warmup="0" retry-threshold="">
                                <parameter-set name="with compactors; parallel processing">
                                    <parameter name="0"
                                               value="/Users/tfidry/Project/Humbug/box/tests/Benchmark/../../fixtures/bench/with-compactors"
                                               type="string"/>
                                    <parameter name="1" value="1" type="boolean"/>
                                </parameter-set>
                                <iteration time-net="2906618" time-revs="1" time-avg="2906618" mem-peak="44786264"
                                           mem-real="46137344" mem-final="13898408" comp-z-value="-1.5374591814251"
                                           comp-deviation="-2.0528834996255"/>
                                <iteration time-net="2995369" time-revs="1" time-avg="2995369" mem-peak="44786264"
                                           mem-real="46137344" mem-final="13898408" comp-z-value="0.70237692867087"
                                           comp-deviation="0.93784474072969"/>
                                <iteration time-net="2966490" time-revs="1" time-avg="2966490" mem-peak="44786264"
                                           mem-real="46137344" mem-final="13898408" comp-z-value="-0.026451220008696"
                                           comp-deviation="-0.035318838871861"/>
                                <iteration time-net="2971250" time-revs="1" time-avg="2971250" mem-peak="44786264"
                                           mem-real="46137344" mem-final="13898408" comp-z-value="0.093678354689692"
                                           comp-deviation="0.12508348250019"/>
                                <iteration time-net="2997153" time-revs="1" time-avg="2997153" mem-peak="44786264"
                                           mem-real="46137344" mem-final="13898408" comp-z-value="0.74740028187716"
                                           comp-deviation="0.99796191327754"/>
                                <iteration time-net="2950184" time-revs="1" time-avg="2950184" mem-peak="44786264"
                                           mem-real="46137344" mem-final="13898408" comp-z-value="-0.4379707252675"
                                           comp-deviation="-0.58479788347115"/>
                                <iteration time-net="2919023" time-revs="1" time-avg="2919023" mem-peak="44786264"
                                           mem-real="46137344" mem-final="13898408" comp-z-value="-1.2243904053466"
                                           comp-deviation="-1.6348602230246"/>
                                <iteration time-net="3052037" time-revs="1" time-avg="3052037" mem-peak="44786264"
                                           mem-real="46137344" mem-final="13898408" comp-z-value="2.1325245629163"
                                           comp-deviation="2.8474411162573"/>
                                <iteration time-net="2945044" time-revs="1" time-avg="2945044" mem-peak="44786264"
                                           mem-real="46137344" mem-final="13898408" comp-z-value="-0.56769047609727"
                                           comp-deviation="-0.75800543217963"/>
                                <iteration time-net="2972213" time-revs="1" time-avg="2972213" mem-peak="44786264"
                                           mem-real="46137344" mem-final="13898408" comp-z-value="0.11798187999107"
                                           comp-deviation="0.15753462440802"/>
                                <stats max="3052037" mean="2967538.1" min="2906618" mode="2966379.2328767" rstdev="1.3352442291981"
                                       stdev="39623.881229506" sum="29675381" variance="1570051963.69"/>
                            </variant>
                        </subject>
                    </benchmark>
                    <result key="time" class="PhpBench\Model\Result\TimeResult"/>
                    <result key="mem" class="PhpBench\Model\Result\MemoryResult"/>
                    <result key="comp" class="PhpBench\Model\Result\ComputedResult"/>
                </suite>
            </phpbench>

            XML;

        yield [
            $xml,
            [
                'no compactors' => 2_638_316.8,
                'with compactors; no parallel processing' => 2_612_822.4,
                'with compactors; parallel processing' => 2_967_538.1,
            ],
        ];
    }
}
