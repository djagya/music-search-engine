import styles from '../Chart.module.scss';
import React from 'react';
import { FIELDS, formatDuration, LABELS } from '../ChartPage';
import { Th } from './Grid';

export default function SongTable({ rows, charted }: { rows: any[]; charted: boolean }) {
  return (
    <table className={styles.table}>
      <thead>
      <tr>
        {charted && <th>Rank</th>}
        <th />

        <Th name={FIELDS.release} label={LABELS[FIELDS.release]} />
        <Th name={FIELDS.artist} label={LABELS[FIELDS.artist]} />
        <Th name={FIELDS.song} label={LABELS[FIELDS.song]} />
        <th>Label</th>
        <th>Genre</th>
        <th>Released</th>

        <th>Data</th>
      </tr>
      </thead>

      <tbody>
      {rows.map(row => (
        <tr key={row._id}>
          <td>{row.cover_art_url && <img src={row.cover_art_url} width="50" alt={row.release_title} />}</td>
          <td>{row.artist_name}</td>
          <td>{row.release_title}</td>
          <td>
            {row.song_name}
            {row.song_duration && <small>&nbsp;{formatDuration(row.song_duration)}</small>}
          </td>
          <td>{row.label_name}</td>
          <td>{row.release_genre}</td>
          <td>{row.release_year_released}</td>
          {/*<td>{JSON.stringify({ isrc: row.song_isrc, upc: row.release_upc })}</td>*/}
          <td>{JSON.stringify(row)}</td>
        </tr>
      ))}
      </tbody>
    </table>
  );
}
