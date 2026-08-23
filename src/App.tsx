/**
 * ВНИМАНИЕ: весь сайт «КИНОМЕТР» намеренно собран В ОДНОМ ФАЙЛЕ — index.html
 * (инлайновые стили + инлайновый JS, без внешних ассетов). Это сделано для
 * деплоя одним файлом на AwardSpace, как просил заказчик.
 *
 * `vite build` просто копирует этот самодостаточный index.html в dist/,
 * поэтому рядом с ним нужно загрузить только api.php (бэкенд MySQL).
 *
 * Этот React-компонент в прод-сборке не используется.
 */
export default function App() {
  return (
    <div style={{ fontFamily: "monospace", padding: 40, color: "#e8b84b", background: "#0d0d0f", minHeight: "100vh" }}>
      КИНОМЕТР: весь сайт живёт в одном файле — index.html (+ api.php для MySQL).
    </div>
  );
}
