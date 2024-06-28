# Gherkin Lint

The Gherkin Lint task will lint your Gherkin feature files.
It lives under the `gherkin_lint` namespace and has following configurable parameters:

```yaml
# grumphp.yml
grumphp:
    tasks:
        gherkin_lint:
            directory: 'features'
            config: ~
```

**directory**

*Default: 'features'*

This option will specify the location of your Gherkin feature files.
By default, the Behat preferred `features` folder is chosen.

**config**

*Default: null*

By default, all rules are enabled. To customize or disable them, create a configuration file named `gherkinlint.json` in the current directory. You need to set the path value in the configuration parameters.
