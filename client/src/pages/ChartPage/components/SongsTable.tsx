import styles from '../Chart.module.scss';
import React from 'react';
import { FIELDS, LABELS } from '../ChartPage';
import { Th } from './Grid';
import { formatDuration } from "../../../utils";

// todo: implement sorting by timestamp for spins, and other fields for both indexes
export default function SongTable({
  rows,
  charted,
  currentSort,
  onSortChange,
}: {
  rows: any[];
  charted: boolean;
  currentSort: string | null;
  onSortChange: any;
}) {
  const hasTimestamp = rows[0] && rows[0].spin_timestamp;

  return (
    <table className={styles.table}>
      <thead>
      <tr>
        {charted && <th>Rank</th>}
        {hasTimestamp && (
          <Th
            name="spin_timestamp"
            label="Timestamp"
            sortable
            currentSort={currentSort}
            onSortChange={onSortChange}
          />
        )}
        <th />

        <Th name={FIELDS.artist} label={LABELS[FIELDS.artist]} />
        <Th
          name={FIELDS.song}
          label={LABELS[FIELDS.song]}
          sortable
          currentSort={currentSort}
          onSortChange={onSortChange}
        />
        <Th name={FIELDS.release} label={LABELS[FIELDS.release]} />
        <Th name={'label_name'} label="Label" />
        <Th name={'release_genre'} label="Genre" />
        <Th name={'release_year_released'} label="Released" placeholder={'[year] or [from]-[to]'} />

        <th>Data</th>
      </tr>
      </thead>

      <tbody>
      {rows.map(row => (
        <tr key={row._id}>
          {charted && <td>{row.rank}</td>}
          {hasTimestamp && <td>{row.spin_timestamp}</td>}
          <td>{row.cover_art_url && <img src={row.cover_art_url} width="50" alt={row.release_title} />}</td>

          <td>{row.artist_name}</td>
          <td>
            {row.song_name}
            {row.song_duration && <small>&nbsp;{formatDuration(row.song_duration)}</small>}
          </td>
          <td>{row.release_title}</td>

          <td>{row.label_name}</td>
          <td>{row.release_genre}</td>
          <td>{row.release_year_released}</td>
          <td>
            {row.release_various_artists == 1 && <span>V/A</span>}
            {row.release_medium && <span>{row.release_medium}</span>}
            {row.song_isrc && (
              <span>
                  <b>ISRC:</b> {row.song_isrc}
                </span>
            )}
            {row.release_upc && (
              <span>
                  <b>UPC:</b> {row.release_upc}
                </span>
            )}
          </td>
        </tr>
      ))}
      </tbody>
    </table>
  );
}

function formatData(data: any) {
}
