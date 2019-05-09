# DOCX to JATS XML Converter Plugin 
The plugin for OJS 3.1+ that allows to convert articles in DOCX to JATS XML format. The output is compatible with Texture plugin - JATS XML online editor.

DOCX is an archive that complies with Open Office XML format. It is commonly used for creation and editing of text documents, including as an exchange format for scientific articles, mainly between an author and a publisher. The converter is designed to support the output from MS Word, LibreOffice Writer, and Google Docs.    

This plugin is aimed at helping publishers that are using JATS XML as a pivotal format for their publication workflow. The idea behind the converter is to create a basic structure of the document from given DOCX file and then prepare the manuscript for the production by JATS XML online editor, like the Texture plugin. The converter is written in pure PHP and doesn't require any additional external extensions for basic functionality.

## Installation
The plugin can be installed in two ways, 1) by downloading the latest stable release or 2) by cloning the master branch. The latter can be accomplished with Git by cloning the repo with submodules into `plugins/generic` directory starting from the web root of OJS instance. It's as simple as: `git clone --recurse-submodules https://github.com/Vitaliy-1/docxConverter.git`.

After installation plugin should be activated in the plugins menu: `Settings -> Website -> Plugins -> check enable near DOCX to JATS XML Converter Plugin`. The button `Convert to JATS XML` will appear as a dropdown under Draft DOCX file on any Workflow stage.

_Important note:_ as parsing references is not yet supported, the only way to add them is manually with available JATS XML WYSIWYG editor - [Texture Plugin](https://github.com/pkp/texture) for OJS 3.1+. How to use them together: ![](https://e-medjournal.com/suppl/convert.gif)

## What article elements are supported? 
It is planned that DOCX to JATS XML Converter will support all major features of DOCX. The table below lists elements that are already supported and are planned to be developed in near future. The row `Planned for the 1.0.0 release` means that it's likely to be included in the first stable release, otherwise it's planned to be included later.

| Feature                     | Supported        | Planned for the 1.0.0 release | Notes |
|-----------------------------|------------------|-------------------------------|-------|
|Paragraphs                   |:heavy_check_mark:|                               |All formatted text in the paragraph can be neste, e.g. **bold, _bold + italic_**. |
|**Bold**                     |:heavy_check_mark:|                               |       |
|*Italic*                     |:heavy_check_mark:|                               |       |
|Text <sup>superscript</sup>  |:heavy_check_mark:|                               |       |
|Text <sub>Subscript</sub>    |:heavy_check_mark:|                               |       |
|~~Strikethrough~~            |:heavy_check_mark:|                               |       |
|Lists                        |:heavy_check_mark:|                               |Can be nested |
|List style                   |                  |:heavy_check_mark:             |       |
|Headings and sections        |:heavy_check_mark:|                               |Can be nested; OOXML headings are tranformed to the JATS XML sections with title and correspondent level |
|Tables                       |:heavy_check_mark:|                               |       |
|Cells with row- and colspan  |:heavy_check_mark:|                               |       |
|Table caption                |                  |:heavy_check_mark:             |       |
|JPEG and PNG Figures         |                  |:white_check_mark: (Partially) |       |
|Figure caption               |                  |:heavy_check_mark:             |       |
|Diagrams                     |                  |                               |       |
|Formulas                     |                  |                               |       |
|Footnotes                    |                  |                               |       |
|MS Word citations            |                  |:white_check_mark:             |       |
|Zotero citations             |                  |                               |       |
|External links               |:heavy_check_mark:|                               |       |
|OOXML metadata               |                  |                               |OOXML contains limited set of metadata and this feature is rarely used by authors|
|Article's metadata from OJS  |:white_check_mark:|                               |Metadata, like authors names, their affiliation, and article title is transfered from OJS; doesn't support abstracts yet|

## How to achieve best results?
The best results can be obtained only with articles that are structured. DOCX to JATS Converter Plugin should work with DOCX files produced by Google Docs, MS Word, and LibreOffice Writer. Although, there can be some drawbacks because these formats are not fully intercompatible.

### Google Docs 
The link to the general example: [google document](https://docs.google.com/document/d/1O3m27j1UgQ6YXPZCBZ9pR-j5xHQ6byOuo7-WngfY-p8/edit?usp=sharing).
When working with Google Docs it should be kept in mind that it doesn't support citations, reference list, figure and table caption. General recommendations:
* _Sections and Headings._ To distinguish sections of scientific articles built-in headings can be used. Headings level represents the level of the section, thus they can be nested. Start a new line, choose a text style from a dropdown menu in the left upper corner: heading 1, heading 2 or another level. By default the text style is normal text. Guideline on Youtube: https://www.youtube.com/watch?v=q58KRXwg93E. Note: there is no need to create table of content.
* _Formatted text._ Bold, italic, and other text formatting is fully supported. The correspondent menu items are situated at the center position of the toolbar.
* _Tables and figures._ Tables and figures can be attached using `insert` button on the left-top side of the toolbar. Cells merging is supported. Unfortunately, caption are not supported by the Google Docs. How to create a table: https://www.youtube.com/watch?v=5HkarJaViQU; how to insert an image: https://www.youtube.com/watch?v=5Eh5WmTJ6qo
* _Lists._ Lists can be inserted using the items on a toolbar that are positioned to the right from formatted text options. Nested lists are supported, to change the level of the list item press `tab` (level down) or `shift + tab` (level up) keyboard buttons while the cursor is on the needed list item. How to manage lists: https://www.youtube.com/watch?v=g2UhdpozSdQ
* _Export as DOCX._ `File -> Download as -> Microsoft Word (.docx)`
* _Upload to OJS._ Produced file can be download to the Copyediting or Production stage as a Draft File, `Convert to JATS XML` button will appear in the dropdown menu under the file.
### LibreOffice and MS Word
Coming soon.  

## Troubleshooting
For proposals and bugs tracking please open an issue on the [converter's page](https://github.com/Vitaliy-1/docxToJats/issues). 
