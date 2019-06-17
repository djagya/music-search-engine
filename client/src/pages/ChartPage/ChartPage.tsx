import React, { useRef, useState } from 'react';
import styles from './Chart.module.scss';
import { Heading } from '../../components/UI';
import { fetchChartRows } from '../../data';
import { ChartResponse } from '../../types';
import SongsTable from './components/SongsTable';
import ReleasesTable from './components/ReleasesTable';
import ArtistsTable from './components/ArtistsTable';
import { ChartModeSwitch, OptionSelect } from './components/GridSettings';
import Grid from './components/Grid';

const TYPE_SONGS = 'songs';
const TYPE_ARTISTS = 'artists';
const TYPE_RELEASES = 'releases';

export const FIELDS: any = {
  artist: 'artist_name',
  release: 'release_title',
  song: 'song_name',
};

export const LABELS: any = {
  artist_name: 'Artist',
  release_title: 'Release',
  song_name: 'Song',
};

export const PAGE_SIZE = 25;

/**
 * todo: "chart mode" should probably use data only from the 'spins' index? it doesn't make sense to chart epf data?
 *
 */
export default function ChartPage() {
  const [gridType, setGridType] = useState(TYPE_SONGS);
  const [chartMode, setChartMode] = useState<boolean>(false);
  const [query, setQuery] = useState({ artist: '', release: '', song: '' });
  const [page, setPage] = useState<number>(0);
  const [response, setResponse] = useState<ChartResponse | null>(null);
  const [index, setIndex] = useState<string>('spins');
  const formNode = useRef<HTMLFormElement>(null);

  /**
   * Collect and send form data.
   */
  function fetchData(page: number = 0) {
    const formData = Array.from(new FormData(formNode.current!).entries()).reduce((acc, [key, value]) => {
      return { ...acc, [key]: value.toString() };
    }, {});

    console.log('query body', formData);
    fetchChartRows(formData, { page, pageSize: PAGE_SIZE, index }).then(res => {
      console.log('response', res);

      if ('error' in res) {
        throw new Error(res.error);
      }

      setResponse(res);
    });
  }

  function renderTable() {
    const rows = (response && response.rows) || [];

    if (gridType === TYPE_SONGS) return <SongsTable rows={rows} charted={chartMode} />;
    if (gridType === TYPE_ARTISTS) return <ArtistsTable rows={rows} charted={chartMode} />;
    if (gridType === TYPE_RELEASES) return <ReleasesTable rows={rows} charted={chartMode} />;
  }

  return (
    <div className={styles.container}>
      <form
        onSubmit={(e: any) => {
          e.preventDefault();
          fetchData(0);
        }}
        ref={formNode}
      >
        <Heading>Chart</Heading>

        <GridSettings
          gridType={gridType}
          index={index}
          chartMode={chartMode}
          onTypeChange={e => setGridType(e.currentTarget.value)}
          onIndexChange={e => setIndex(e.currentTarget.value)}
          onChartModeChange={e => setChartMode(e.currentTarget.checked)}
        />

        <button className={styles.submit} type="submit">
          Search
        </button>

        <Grid response={response} page={page} onPageChange={(p: number) => fetchData(p)}>
          {renderTable()}
        </Grid>
      </form>
    </div>
  );
}

function GridSettings({
  gridType,
  index,
  chartMode,
  onTypeChange,
  onIndexChange,
  onChartModeChange,
}: {
  gridType: string;
  index: string;
  chartMode: boolean;
  onTypeChange: { (e: React.ChangeEvent<HTMLInputElement>): void };
  onIndexChange: { (e: React.ChangeEvent<HTMLInputElement>): void };
  onChartModeChange: { (e: React.ChangeEvent<HTMLInputElement>): void };
}) {
  return (
    <div className={styles.gridSettings}>
      <OptionSelect
        name="type"
        items={{ [TYPE_SONGS]: 'Songs', [TYPE_ARTISTS]: 'Artists', [TYPE_RELEASES]: 'Releases' }}
        active={gridType}
        onChange={onTypeChange}
      >
        Search for:
      </OptionSelect>
      <OptionSelect name="index" items={{ spins: 'Spins', epf: 'Apple Music' }} active={index} onChange={onIndexChange}>
        in:
      </OptionSelect>
      <ChartModeSwitch chartMode={chartMode} onChange={onChartModeChange} />
    </div>
  );
}

export function formatDuration(duration: number) {
  return `${Math.floor(duration / 1000 / 60)}:${`0${Math.floor((duration / 1000) % 60)}`.slice(-2)}`;
}
