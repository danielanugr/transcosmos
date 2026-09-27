# Task Management Frontend (Next.js)

Production-ready dashboard interface built with **Next.js (App Router)**, **TypeScript**, and **Tailwind CSS**. It connects to the Laravel REST API backend to provide real-time task management, file uploads with chunking, and interactive commenting.

## Features Implemented

1. **Authentication**:
   - Secure login form with validation.
   - Quick one-click demo credentials (`Admin: alice@example.com`, `Member: bob@example.com`).
   - JWT session management and bearer token injection.
   - User profile indicator and logout mechanism.

2. **Task Dashboard & CRUD**:
   - Create, read, update, and delete tasks.
   - Task filtering by status (`pending`, `in_progress`, `completed`, `cancelled`).
   - Priority filter (`low`, `medium`, `high`, `urgent`) and keyword search.
   - Multi-field sorting (`created_at`, `due_date`, `priority`, `title`) and pagination.
   - Bulk status updates for selected tasks.

3. **Real-time Task Updates**:
   - Polling synchronization interval with manual refresh trigger.
   - Live visual indicators for task state changes.

4. **File Uploads & Attachments**:
   - Drag-and-drop file upload zone.
   - Automatic chunked upload for files exceeding 5MB to handle large attachments reliably.
   - Progress bar indicators with byte and chunk counter status.
   - Thumbnail indicators, version tracking (`v1`, `v2`), and direct download links.

5. **Comments System**:
   - Live comment thread per task.
   - Add new comment with keyboard shortcut (`Ctrl/Cmd + Enter`).
   - Author ownership and delete permissions.

6. **Feedback & Accessibility**:
   - Self-dismissing toast notifications for all operations.
   - Accessible modal dialogs with Escape key listeners and focus retention.

## Project Structure

```text
frontend/
├── src/
│   ├── app/
│   │   ├── globals.css         # Styling and micro-animations
│   │   ├── layout.tsx          # Root layout with providers
│   │   └── page.tsx            # Main view orchestration
│   ├── components/
│   │   ├── Auth/               # Login components
│   │   ├── Dashboard/          # Header, stats, filters, cards, modals
│   │   ├── Attachments/        # Drag & drop upload and attachment list
│   │   ├── Comments/           # Comment feed and form
│   │   └── UI/                 # Modals, toasts, and badges
│   └── lib/
│       ├── api.ts              # REST API client
│       ├── authContext.tsx     # Authentication context
│       └── types.ts            # Domain entity interfaces
├── public/                     # Static assets and favicon
├── tests/                      # Unit and integration test suite
├── next.config.ts              # Next.js configuration
├── package.json                # Project dependencies
└── README.md                   # Documentation
```

## Getting Started

### 1. Install Dependencies
```bash
npm install
```

### 2. Configure Environment
Verify `.env.local` contains the backend API URL:
```env
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api
```

### 3. Run Development Server
```bash
npm run dev
```
Open [http://localhost:3000](http://localhost:3000) in your browser.

### 4. Run Test Suite
```bash
npm test
```

### 5. Production Build
```bash
npm run build
npm run start
```
