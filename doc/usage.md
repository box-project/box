Creating a PHAR should be as simple as running `box compile` (**no config required!**). It will however assume some
defaults that you might want to change. Box will by default be looking in order for the files `box.json` and
`box.json.dist` in the current working directory. A basic configuration could be for example changing the PHAR
permissions:

```json
{
    "chmod": "0700"
}
```

You can then find more advanced configuration settings in [the configuration documentation](configuration.md).
For more information on which command or options is available, you can run:

```shell
box help
```

<br />
<hr />

« [Installation](installation.md#installation) • [Configuration](configuration.md#configuration) »
