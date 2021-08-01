<style>
  .md-typeset h1,
  .md-content__button {
    display: none;
  }
</style>

![Box logo](img/box.png){: .box-image}

The Box application simplifies the PHAR building process. Out of the box (no pun intended), the application can do many
great things:

- ⚡  Fast application bundling
- 🔨 [PHAR isolation](code-isolation.md#phar-code-isolation)
- ⚙️ Zero configuration by default
- 🚔 [Requirements checker](requirement-checker.md#requirements-checker)
- 🚨 Friendly error logging experience
- 🔍 Retrieve information about the PHAR extension or a PHAR file and its contents (`box info` or `box diff`)
- 🔐️ Verify the signature of an existing PHAR (`box verify`)
- 📝 Use Git tags and short commit hashes for versioning
- 🕵️️ Get recommendations and warnings about regarding your configuration (`box validate`)
- 🐳 [Docker support (`box docker`)](docker.md#docker-support)
