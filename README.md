# HTML to PDF AAS (Asynchronous API Service)

This is a minimal HTML to PDF / image conversion service built using:

- [FrankenPHP](https://github.com/dunglas/frankenphp)
- [wkhtmltopdf](https://wkhtmltopdf.org/)
- PHP (no frameworks or dependencies)

It accepts JSON-based POST requests to generate PDFs from raw HTML content or a remote URL.

## 📦 Docker Setup

This project is packaged in a lightweight Docker container built on top of [`surnet/alpine-wkhtmltopdf:small`](https://hub.docker.com/r/surnet/alpine-wkhtmltopdf).

### Build the Docker image

```bash
docker build -t wkhtml-aas .
```

### Run the container

```bash
docker run -p 8080:80 wkhtml-aas
```

Once running, the service will be available at:  
**`http://localhost:8080/pdf`**

## 📥 API Usage

### Endpoints

```
POST /pdf
POST /png
POST /jpeg
POST /tiff
```

### Content-Type

```
application/json
```

### Request Body Format

```json
{
  "html": "<html><body><h1>Hello World</h1></body></html>",
  "url": "", // optional
  "params": {
    "orientation": "Portrait",
    "page-size": "A4",
    "margin-top": "10",
    "margin-bottom": "10",
    "margin-left": "15",
    "margin-right": "15",
    "header-center": "Header",
    "footer-html": "<html><body><p>Footer</p></body></html>"
  }
}
```

- **`html`** – (optional) Raw HTML content to convert.
- **`url`** – (optional) If provided, fetch and convert this URL instead of `html`.
- **`params`** – PDF rendering options:
  - `orientation`: `Portrait` or `Landscape`
  - `page-size`: e.g. `A4`, `Letter`
  - `margin-top`, `margin-bottom`, `margin-left`, `margin-right`: in millimeters
  - **Visit https://wkhtmltopdf.org/usage/wkhtmltopdf.txt for more information about wkhtml parameters**

### Example: Generate PDF from raw HTML

```bash
curl -X POST http://localhost:8080/pdf \
  -H "Content-Type: application/json" \
  -d '{
    "html": "<html><body><h1>Hello PDF</h1></body></html>",
    "params": {
      "orientation": "Portrait",
      "page-size": "A4",
      "margin-top": "10",
      "margin-bottom": "10",
      "header-center": "Header",
      "footer-html": "<html><body><p>Footer</p></body></html>"
    }
  }' --output output.pdf
```

## 📄 Output

- Returns a PDF / image file directly in the response body.
- Response headers sample:
  ```
  Content-Type: application/pdf
  Content-Disposition: inline; filename="output.pdf"
  ```

## 🛠 Notes

- Only one of `html` or `url` should be provided.
- FrankenPHP runs PHP as a fast, async worker – perfect for containerized services.

## 🤔 Why FrankenPHP Instead of Python?

This service is built with **FrankenPHP** instead of a Python-based stack to take advantage of its **lightweight, high-concurrency architecture**. FrankenPHP runs as a blazing-fast, production-grade PHP server with native support for async workers and HTTP/1.1/2.0. Unlike typical Python solutions (e.g. Flask + Gunicorn), FrankenPHP offers **built-in concurrency without needing additional layers or process managers**, resulting in faster response times and lower overhead. Thanks to its minimal runtime and efficient memory footprint, the final Docker image is **significantly smaller** than equivalent Python-based containers, making it ideal for microservices, cold starts, and edge deployments.

## 📬 License

MIT – Use freely and modify as needed.
