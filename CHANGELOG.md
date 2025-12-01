# Changelog

All notable changes to `filament-media-gallery` will be documented in this file.

## 2.0.0 - 2025-12-01

### 🚀 Features & Improvements

- **Internationalization (i18n):** The entire codebase, including comments, variables, and function names, has been translated from Portuguese to English. This makes the package more accessible to a global audience.
- **README Update:** The `README.md` has been completely translated to English and updated to reflect all recent changes.
- **Code Clarity:** Improved logging messages throughout the traits for easier debugging.

### 💥 Breaking Changes

- **Method Renaming:** Public and protected methods in traits have been renamed to English, which may require updates in your code if you were extending or calling them directly.
  - `HasMediaGallery::carregarMaisMedias()` is now `HasMediaGallery::loadMoreMedia()`.
  - `ProcessesVideoThumbnails::gerarThumbnailVideo()` is now `ProcessesVideoThumbnails::generateVideoThumbnail()`.
  - `ProcessesVideoThumbnails::obterDuracaoVideo()` is now `ProcessesVideoThumbnails::getVideoDuration()`.
- **Database Migrations:** The column names in the `media` table have been changed.
  - `nome_original` is now `original_name`.
  - `tamanho` is now `size`.
  You will need to create a new migration to alter these columns if you are upgrading from a previous version.

## 1.0.0 - 202X-XX-XX

- initial release
