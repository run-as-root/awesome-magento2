# Development details of this repository

### Overview
Content is managed via various files in the `content/` folder: Markdown files, CSV files, JSON files. The main content is written away in a file `content/main.md` which then includes other files by using custom tags:

    {% file=foobar.csv parser="AwesomeList\Parser\GenericCsvList" %}

When generating the content, the `parser` class is instantiated with the `file` as an argument. Next, the parser returns Markdown content, which then replaces the tag. The end result of this parsed `content/main.md` file is written to the main `README.md` in the root of this repository, thus forming the Awesome List.

### Generating content
To generate content, check out this repository. Next, run `composer install`. Next, run the script `generate.php`.