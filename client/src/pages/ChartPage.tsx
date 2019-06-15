import React, { useState } from 'react';
import styles from './Chart.module.scss';
import { Heading } from '../components/UI';
import { fetchChartRows } from '../data';

const TYPES: any = {
  song: 'Songs',
  artist: 'Artists',
  release: 'Releases',
};

const FIELDS: any = {
  artist: 'artist',
  release: 'release',
  song: 'song',
};

const PAGE_SIZE = 50;

interface ChartResponse {
  totalCount: number;
  page: number;
  rows: any[];
}

/**
 * todo: "chart mode" should probably use data only from the 'spins' index? it doesn't make sense to chart epf data?
 *
 */
export default function ChartPage() {
  const [gridType, setGridType] = useState('song');
  const [chartMode, setChartMode] = useState<boolean>(false);
  const [query, setQuery] = useState({ artist: '', release: '', song: '' });
  const [page, setPage] = useState<number>(0);
  const [response, setResponse] = useState<ChartResponse | null>(null);

  /**
   * Collect and send form data.
   */
  function handleSubmit(e: any) {
    e.preventDefault();
    const formData = Array.from(new FormData(e.currentTarget).entries()).reduce((acc, [key, value]) => {
      return { ...acc, [key]: value.toString() };
    }, {});

    console.log(formData);

    fetchChartRows(formData, page).then(res => {
      console.log(res);
      if ('error' in res) {
        throw new Error(res.error);
      }

      setResponse(res);
    });
  }

  return (
    <div className={styles.container}>
      <form onSubmit={handleSubmit}>
        <Heading>Chart</Heading>

        <div>
          <TypeSelect gridType={gridType} onChange={(e: any) => setGridType(e.currentTarget.value)} />

          <ChartModeSwitch chartMode={chartMode} onChange={(e: any) => setChartMode(e.currentTarget.checked)} />
        </div>

        <button type="submit">Search</button>

        {response && (
          <div>
            {response && <div>Total: {response.totalCount}</div>}

            {gridType === TYPES.song && <SongTable rows={response.rows} charted={chartMode} />}
            {gridType === TYPES.artist && <ArtistTable rows={response.rows} charted={chartMode} />}
            {gridType === TYPES.release && <ReleaseTable rows={response.rows} charted={chartMode} />}

            {response && (
              <Pagination totalCount={response.totalCount} page={page} onClick={(p: number) => setPage(p)} />
            )}
          </div>
        )}
      </form>
    </div>
  );
}

function TypeSelect({ gridType, onChange }: { gridType: string; onChange: any }) {
  return (
    <div className={styles.TypeSelect}>
      <b>Search for:</b>&nbsp;
      <ul>
        {Object.keys(TYPES).map(type => (
          <li key={type}>
            <label>
              <input type="radio" name="type" value={type} checked={type === gridType} onChange={onChange} />
              &nbsp;
              {TYPES[type]}
            </label>
          </li>
        ))}
      </ul>
    </div>
  );
}

function ChartModeSwitch({ chartMode, onChange }: { chartMode: boolean; onChange: any }) {
  return (
    <label>
      Chart? <input type="checkbox" name="chartMode" checked={chartMode} onChange={onChange} />
    </label>
  );
}

function SongTable({ rows, charted }: { rows: any[]; charted: boolean }) {
  return (
    <table className={styles.table}>
      <thead>
      {charted && <th>Rank</th>}
      <th>Cover art</th>

      <Th field={FIELDS.artist} />
      <Th field={FIELDS.release} />
      <Th field={FIELDS.song} />
      <th>Label</th>
      <th>Genre</th>
      <th>Released</th>

      <th>Data</th>
      </thead>

      <tbody>
      {rows.map(row => (
        <tr key={row._id}>
          <td>{row.cover_art_url && <img src={row.cover_art_url} width="50" />}</td>
          <td>{row.artist_name}</td>
          <td>{row.release_title}</td>
          <td>
            {row.song}
            {row.song_duration && <small>&nbsp;{row.song_duration}</small>}
          </td>
          <td>{row.label_name}</td>
          <td>{row.release_genre}</td>
          <td>{row.release_year_released}</td>
          <td>{JSON.stringify({ isrc: row.song_isrc, upc: row.release_upc })}</td>
        </tr>
      ))}
      </tbody>
    </table>
  );
}

/**
 * Group by artist name.
 * Grouped columns: label.
 */
function ArtistTable({ rows, charted }: { rows: any[]; charted: boolean }) {
  return (
    <table className={styles.table}>
      <thead>
      {charted && <th>Rank</th>}

      <Th field={FIELDS.artist} />
      <th>Labels</th>
      </thead>

      <tbody>
      {rows.map(row => (
        <tr key={row._id}>
          <td>{row.artist_name}</td>
          <td>{JSON.stringify(row.label_name)}</td>
        </tr>
      ))}
      </tbody>
    </table>
  );
}

/**
 * Group by release title.
 * Grouped columns: label, genre, release date.
 */
function ReleaseTable({ rows, charted }: { rows: any[]; charted: boolean }) {
  return (
    <table className={styles.table}>
      <thead>
      <th>Cover art</th>
      <Th field={FIELDS.release} />
      <Th field={FIELDS.artist} />
      <th>Genres</th>
      <th>Labels</th>
      <th>Released</th>
      </thead>

      <tbody>
      <tr>
        <td>release</td>
        <td>artist1, artist2</td>
        <td>genre1, genre2</td>
        <td>label1, label2</td>
        <td>released1, released2</td>
      </tr>
      </tbody>
    </table>
  );
}

function Th({ field }: { field: string }) {
  return (
    <th>
      {FIELDS[field]}
      <input type="text" name={`query[${field}]`} />
    </th>
  );
}

function Pagination({ totalCount, page, onClick }: { totalCount: number; page: number; onClick: any }) {
  const VISIBLE_PAGES_LIMIT = 10;

  const pagesCount = Math.ceil(totalCount / PAGE_SIZE);
  const displayPages = Math.max(pagesCount, VISIBLE_PAGES_LIMIT);

  return (
    <div>
      Pagination:
      <ul style={{ display: 'flex' }}>
        {Array.from(Array(displayPages).keys()).map(p => (
          <li key={p}>
            <a href="#" className={page === p ? styles.activePage : undefined} onClick={onClick}>
              {p + 1}
            </a>
          </li>
        ))}

        {displayPages < pagesCount && <li>...</li>}
      </ul>
    </div>
  );
}
