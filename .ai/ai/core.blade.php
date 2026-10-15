@php
/** @var \LaraGram\Brain\Install\GuidelineAssist $assist */
@endphp
# LaraGram AI SDK

- `laraxgram/ai` is the LaraGram port of the Laravel AI SDK (same API under `LaraGram\Ai`) with Telegram support: agents, tools, structured output, streaming, queueing, images, audio, transcription, embeddings, reranking, classification, provider files and vector stores. Configuration lives in `config/ai.php`.
- IMPORTANT: Activate `ai-development` whenever you create or change agents (`{{ $assist->commanderCommand('make:agent') }}`), tools (`{{ $assist->commanderCommand('make:tool') }}`), agent middleware, structured output or Telegram outputs, or any call to `Image`, `Audio`, `Transcription`, `Embeddings`, `Reranking`, `Classification`, `Files` or `Stores`.
- In a bot, return the AI response from the listen (`prompt()`, `stream()`, `queue()`, `Image::...->generate()`): it is sent to the chat. Use `->sendTo($chat)` outside a listen. Do not cast a response to a string and call `sendMessage` yourself.
- Telegram files are uploads: pass `$request->file()` as an attachment or to `fromUpload()`.
- A provider call takes seconds: use `queue()` for long work and `stream()` when the user should watch the answer.
- Never invent an API: everything mirrors the Laravel AI SDK, so search the docs (`search-docs`) or read `vendor/laraxgram/ai/src` before using a method you have not seen.
- Never log or store a provider key, and keep an agent's `instructions()` in the class rather than building prompts out of raw user text.
