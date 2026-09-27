// scripts/fetch-news.js
const Parser = require('rss-parser');
const fs = require('fs');
const parser = new Parser();

// Читаем список источников
const sources = JSON.parse(fs.readFileSync('scripts/sources.json', 'utf-8'));

// Ограничение: не больше 30 новостей
const MAX_ITEMS = 30;

async function fetchNews() {
  let allNews = [];

  for (const url of sources) {
    try {
      console.log(`Загружаю: ${url}`);
      const feed = await parser.parseURL(url);
      
      feed.items.forEach(item => {
        // Извлекаем картинку (если есть)
        const image = item.enclosure?.url || 
                      item.content?.match(/<img[^>]+src="([^">]+)"/)?.[1] || 
                      '';
        
        allNews.push({
          title: item.title,
          link: item.link,
          pubDate: item.pubDate || item.isoDate,
          contentSnippet: item.contentSnippet || '',
          image: image,
          source: feed.title || url
        });
      });
    } catch (error) {
      console.error(`Ошибка при загрузке ${url}: ${error.message}`);
    }
  }

  // Сортируем по дате (от новых к старым)
  allNews.sort((a, b) => new Date(b.pubDate) - new Date(a.pubDate));

  // Обрезаем до 30 новостей
  allNews = allNews.slice(0, MAX_ITEMS);

  // Сохраняем в файл news.json
  fs.writeFileSync('news.json', JSON.stringify(allNews, null, 2), 'utf-8');
  console.log(`Готово! Собрано ${allNews.length} новостей.`);
}

fetchNews();