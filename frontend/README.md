# Frontend Application (Task Management)

Modern single-page application built with React 19, TypeScript, and Vite, integrating with the Laravel backend REST API.

## Project Structure

```
frontend/
├── src/
│   ├── assets/        # Static assets and icons
│   ├── components/    # Reusable UI components
│   ├── services/      # API client and service integrations
│   ├── App.tsx        # Main application component
│   └── main.tsx       # Application entry point
├── public/            # Static public assets
├── tests/             # Component and unit tests (Vitest)
├── package.json       # Project dependencies and scripts
├── vite.config.ts     # Vite configuration
└── README.md          # Setup and development instructions
```

## Getting Started

### Prerequisites
- Node.js >= 20.x
- npm >= 10.x

### Installation

```bash
# Navigate to frontend directory
cd frontend

# Install dependencies
npm install
```

### Environment Configuration

Create a `.env` file in the `frontend` root:

```env
VITE_API_BASE_URL=http://localhost:8000/api
```

### Available Scripts

- `npm run dev`: Start the local Vite development server with HMR.
- `npm run build`: Type-check and create production build.
- `npm run test`: Run automated unit and integration tests using Vitest.
- `npm run lint`: Run code linter.
- `npm run preview`: Preview production build locally.
