FROM node:20-alpine
WORKDIR /app

COPY package*.json ./
COPY server.js ./

EXPOSE 8001
CMD ["node", "server.js"]
