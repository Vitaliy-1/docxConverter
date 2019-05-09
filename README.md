# DOCX to JATS XML Converter Plugin 
The plugin for OJS 3.1+ that allows to convert articles in DOCX to JATS XML format. The output is compatible with Texture plugin - JATS XML online editor.

DOCX is an archive that complies with Open Office XML format. It is commonly used for creation and editing of text documents, including as an exchange format for scientific articles, mainly between an author and a publisher. The converter is designed to support the output from MS Word, LibreOffice Writer, and Google Docs.    

This plugin is aimed at helping publishers that are using JATS XML as a pivotal format for their publication workflow. The idea behind the converter is to create a basic structure of the document from given DOCX file and then prepare the manuscript for the production by JATS XML online editor, like the Texture plugin. The converter is written in pure PHP and doesn't require any additional external extensions for basic functionality.

## Installation
The plugin can be installed in two ways, 1) by downloading the latest stable release or 2) by cloning the master branch. The latter can be accomplished with Git by cloning the repo with submodules into `plugins/generic` directory starting from the web root of OJS instance. It's as simple as: `git clone --recurse-submodules https://github.com/Vitaliy-1/docxConverter.git`. 

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

## Troubleshooting
For proposals and bugs tracking please open an issue on the [converter's page](https://github.com/Vitaliy-1/docxToJats/issues). 
